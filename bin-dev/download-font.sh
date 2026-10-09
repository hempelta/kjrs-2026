#!/usr/bin/env bash
#
# download-font.sh — Downloads a Google Font via google-webfonts-helper
# and generates the full SCSS structure for the SiteKit build system.
#
# Usage:
#   ./bin-dev/download-font.sh <font-id> [--subset=latin] [--weights=400,600,700]
#
# Examples:
#   ./bin-dev/download-font.sh source-serif-4
#   ./bin-dev/download-font.sh inter --weights=400,500,600,700
#   ./bin-dev/download-font.sh roboto --subset=latin-ext --weights=400,700
#
# What it does:
#   1. Queries the google-webfonts-helper API for font metadata
#   2. Downloads WOFF2 files to Build/Default/Resources/Assets/Website/Fonts/<FontName>/
#   3. Creates Build/Default/src/scss/Fonts/ModernBrowsers/<FontName>.scss (all variants)
#   4. Creates Build/Default/src/scss/Fonts/ModernBrowsers/<FontName>/_<FontName>-<weight>-<subset>.scss
#
# Requirements: python3, curl

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
BUILD_DIR="${PROJECT_ROOT}/Build/Default"

FONT_ASSETS_DIR="${BUILD_DIR}/Resources/Assets/Website/Fonts"
SCSS_FONTS_DIR="${BUILD_DIR}/src/scss/Fonts/ModernBrowsers"

API_BASE="https://gwfh.mranftl.com/api/fonts"

usage() {
    echo "Usage: $0 <font-id> [--subset=latin] [--weights=400,600,700]"
    echo ""
    echo "  font-id     The google-webfonts-helper font ID (e.g. 'source-serif-4', 'inter')"
    echo "  --subset    Character subset to download (default: latin)"
    echo "  --weights   Comma-separated weights to download (default: all available)"
    echo ""
    echo "Find font IDs at: https://gwfh.mranftl.com/fonts"
    exit 1
}

if [[ $# -lt 1 ]]; then
    usage
fi

FONT_ID="$1"
shift

SUBSET="latin"
WEIGHTS=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --subset=*)
            SUBSET="${1#--subset=}"
            shift
            ;;
        --weights=*)
            WEIGHTS="${1#--weights=}"
            shift
            ;;
        *)
            echo "Unknown option: $1"
            usage
            ;;
    esac
done

echo "Fetching font metadata for '${FONT_ID}' ..."

API_URL="${API_BASE}/${FONT_ID}?subsets=${SUBSET}"

API_RESPONSE_FILE=$(mktemp)
trap "rm -f '${API_RESPONSE_FILE}'" EXIT

HTTP_CODE=$(curl -sf -w "%{http_code}" -o "${API_RESPONSE_FILE}" "${API_URL}" 2>/dev/null) || {
    echo "Error: Could not reach the google-webfonts-helper API."
    echo "Check the font ID at: https://gwfh.mranftl.com/fonts"
    exit 1
}

if [[ "${HTTP_CODE}" != "200" ]]; then
    echo "Error: Font '${FONT_ID}' not found (HTTP ${HTTP_CODE})."
    echo "Check the font ID at: https://gwfh.mranftl.com/fonts"
    exit 1
fi

python3 - "${API_RESPONSE_FILE}" "${SUBSET}" "${WEIGHTS}" "${FONT_ASSETS_DIR}" "${SCSS_FONTS_DIR}" << 'PYEOF'
import json
import os
import sys
import urllib.request
import re

api_response_file = sys.argv[1]
subset = sys.argv[2]
weights_filter = sys.argv[3]
font_assets_dir = sys.argv[4]
scss_fonts_dir = sys.argv[5]

with open(api_response_file) as f:
    api_response = json.load(f)

font_id = api_response["id"]
font_family = api_response["family"]
font_version = api_response["version"]
raw_variants = api_response.get("variants", [])

font_dir_name = re.sub(r'\s+', '', font_family)

def parse_variant_id(variant_id):
    if variant_id == "regular":
        return 400, "normal"
    if variant_id == "italic":
        return 400, "italic"
    if variant_id.endswith("italic"):
        weight_str = variant_id[:-6]
        return int(weight_str) if weight_str else 400, "italic"
    return int(variant_id), "normal"

def filename_suffix(variant_id):
    weight, style = parse_variant_id(variant_id)
    if weight == 400 and style == "normal":
        return "regular"
    if weight == 400 and style == "italic":
        return "italic"
    if style == "italic":
        return f"{weight}italic"
    return str(weight)

if weights_filter:
    requested = set(weights_filter.split(","))
    variants = []
    for variant in raw_variants:
        vid = variant["id"] if isinstance(variant, dict) else variant
        weight, _ = parse_variant_id(vid)
        if str(weight) in requested:
            variants.append(variant)
else:
    variants = raw_variants

if not variants:
    all_ids = [v["id"] if isinstance(v, dict) else v for v in raw_variants]
    print(f"Error: No matching variants for weights '{weights_filter}'")
    print(f"Available: {', '.join(all_ids)}")
    sys.exit(1)

font_output_dir = os.path.join(font_assets_dir, font_dir_name)
scss_complete_file = os.path.join(scss_fonts_dir, f"{font_dir_name}.scss")
scss_variants_dir = os.path.join(scss_fonts_dir, font_dir_name)

os.makedirs(font_output_dir, exist_ok=True)
os.makedirs(scss_variants_dir, exist_ok=True)

variant_ids = []
for v in variants:
    if isinstance(v, dict):
        variant_ids.append(v["id"])
    else:
        variant_ids.append(v)

print(f"Font:     {font_family} ({font_id})")
print(f"Version:  {font_version}")
print(f"Subset:   {subset}")
print(f"Variants: {len(variants)} ({', '.join(variant_ids)})")
print(f"Output:   {font_output_dir}")
print(f"SCSS:     {scss_fonts_dir}/{font_dir_name}*")
print()

complete_scss_blocks = []
downloaded_count = 0
skipped_count = 0

sorted_variants = sorted(variants, key=lambda v: (
    parse_variant_id(v["id"] if isinstance(v, dict) else v)[0],
    parse_variant_id(v["id"] if isinstance(v, dict) else v)[1]
))

for variant in sorted_variants:
    if isinstance(variant, dict):
        variant_id = variant["id"]
        woff2_url = variant.get("woff2")
    else:
        variant_id = variant
        woff2_url = None

    weight, style = parse_variant_id(variant_id)
    suffix = filename_suffix(variant_id)
    comment = f"{font_id}-{suffix} - {subset}"

    woff2_filename = f"{font_id}-{font_version}-{subset}-{suffix}.woff2"
    woff2_path = os.path.join(font_output_dir, woff2_filename)

    font_face_block = f"""/* {comment} */
@font-face {{
    font-display: swap;
    font-family: '{font_family}';
    font-style: {style};
    font-weight: {weight};
    src: url('#{{$site-url-prefix}}/Fonts/{font_dir_name}/{woff2_filename}') format('woff2');
}}"""

    complete_scss_blocks.append(font_face_block)

    if style == "italic":
        variant_scss_filename = f"_{font_dir_name}-{weight}italic-{subset}.scss"
    else:
        variant_scss_filename = f"_{font_dir_name}-{weight}-{subset}.scss"

    variant_scss_content = f'@import "../../../Config/Variables";\n{font_face_block}\n'
    variant_scss_path = os.path.join(scss_variants_dir, variant_scss_filename)

    with open(variant_scss_path, "w") as f:
        f.write(variant_scss_content)

    if os.path.exists(woff2_path):
        print(f"  Exists:     {woff2_filename}")
        skipped_count += 1
        continue

    if not woff2_url:
        print(f"  Warning:    No woff2 URL for variant '{variant_id}' — skipping download")
        continue

    try:
        urllib.request.urlretrieve(woff2_url, woff2_path)
        file_size = os.path.getsize(woff2_path)
        print(f"  Downloaded: {woff2_filename} ({file_size // 1024} KB)")
        downloaded_count += 1
    except Exception as e:
        print(f"  Error:      Could not download {woff2_filename}: {e}")

with open(scss_complete_file, "w") as f:
    f.write("\n\n".join(complete_scss_blocks) + "\n")

print()
print(f"Done!")
print(f"  Font files:     {font_output_dir}/  ({downloaded_count} downloaded, {skipped_count} existed)")
print(f"  Complete SCSS:  {scss_complete_file}")
print(f"  Variant SCSS:   {scss_variants_dir}/  ({len(sorted_variants)} files)")
print()
print(f"Usage in Main.scss:")
print(f"  // All variants:")
print(f"  @import 'Fonts/ModernBrowsers/{font_dir_name}';")
print()
print(f"  // Individual variants (granular control):")

for variant in sorted_variants[:3]:
    vid = variant["id"] if isinstance(variant, dict) else variant
    weight, style = parse_variant_id(vid)
    if style == "italic":
        scss_name = f"{font_dir_name}-{weight}italic-{subset}"
    else:
        scss_name = f"{font_dir_name}-{weight}-{subset}"
    print(f"  @import 'Fonts/ModernBrowsers/{font_dir_name}/{scss_name}';")

if len(sorted_variants) > 3:
    print(f"  // ... and {len(sorted_variants) - 3} more")
PYEOF