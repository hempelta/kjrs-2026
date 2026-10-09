#!/usr/bin/env node
'use strict';

const {execSync} = require('child_process');

const SASS_SAFE_VERSION = '1.59.3';

const getPackageVersion = (pkg) => {
  try {
    const output = execSync(`npm list ${pkg} --depth=0 --json`).toString();
    const json = JSON.parse(output);
    return json.dependencies?.[pkg]?.version ?? null;
  } catch (error) {
    return null;
  }
};

const updatePackage = (pkg, version) => {
  console.log(`Installing ${pkg}@${version}...`);
  execSync(`npm install ${pkg}@${version} --save-dev`, {stdio: 'inherit'});
};

const main = async () => {
  console.log('🔍 Checking your Bootstrap and Sass versions...\n');

  const sassVersion = getPackageVersion('sass');
  const bootstrapVersion = getPackageVersion('bootstrap');

  console.log(`Current sass version: ${sassVersion ?? 'Not installed'}`);
  console.log(`Current bootstrap version: ${bootstrapVersion ?? 'Not installed'}`);
  console.log('');

  if (!sassVersion) {
    console.error('❗ sass is not installed.');
    process.exit(1);
  }

  if (!bootstrapVersion) {
    console.error('❗ bootstrap is not installed.');
    process.exit(1);
  }

  const bootstrapMajor = bootstrapVersion.split('.')[0];

  if (bootstrapMajor === '5') {
    console.log('⚠️  Bootstrap 5.x detected.');

    if (sassVersion === SASS_SAFE_VERSION) {
      console.log('✅ Sass already at safe version!');
    } else {
      console.log('⚠️  Current Sass version may cause deprecation warnings.');

      // Prüfen auf Umgebungsvariable oder Parameter
      const autoDowngrade = process.env.AUTO_DOWNGRADE_SASS === 'true' || process.argv.includes('--auto-downgrade');

      if (autoDowngrade) {
        updatePackage('sass', SASS_SAFE_VERSION);
        console.log('✅ Sass downgraded successfully!');
      } else {
        console.log('❗ Continuing with potential warnings.');
      }
    }
  } else {
    console.log('✅ Bootstrap version >= 6 detected or no issues expected.');
  }

  console.log('\n🎉 Done.');
};

main();
