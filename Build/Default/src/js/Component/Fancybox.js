import {Fancybox} from "@fancyapps/ui/dist/fancybox/";

import { de_DE } from "@fancyapps/ui/dist/fancybox/l10n/de_DE.js";
import { en_EN } from "@fancyapps/ui/dist/fancybox/l10n/en_EN.js";
// import { es_ES } from "@fancyapps/ui/dist/fancybox/l10n/es_ES.js";
// import { fr_FR } from "@fancyapps/ui/dist/fancybox/l10n/fr_FR.js";
// import { it_IT } from "@fancyapps/ui/dist/fancybox/l10n/it_IT.js";

function getFancyboxL10n() {
  const htmlLang = (document.documentElement.lang || "en").toLowerCase();

  // Map TYPO3 html lang -> Fancybox locale object
  if (htmlLang.startsWith("de")) return de_DE;
  if (htmlLang.startsWith("en")) return en_EN;
  // if (htmlLang.startsWith("fr")) return fr_FR;
  // if (htmlLang.startsWith("it")) return it_IT;
  // if (htmlLang.startsWith("es")) return es_ES;

  return en_EN;
}

export function initFancybox() {

  Fancybox.bind("[data-fancybox]", {
    l10n: {
      ...getFancyboxL10n(),

      // Ensure the close tooltip text is exactly what we want:
      // This is the text that will be used for the button's title attribute. :contentReference[oaicite:2]{index=2}
      CLOSE: getFancyboxL10n().CLOSE || (document.documentElement.lang?.startsWith("de") ? "Schließen" : "Close"),
    },
  });

  // Currently, the behavior is the same as with data-fancybox-5, but it could be customized.
  // For iframes, `data-fancybox data-type="iframe"` can also be used.
  // Important: Adjustments must also be made in the tracking function in winkel-theme/Resources/Public/Js/WinkelTheme.js.
  Fancybox.bind('[data-fancybox-video]', {});
  Fancybox.bind("[data-fancybox-iframe]", {});


  // Fancybox.bind("[data-fancybox-html]", {
  //   Html: {
  //     autoSize: false,
  //   },
  // });

  // Get all buttons with the class "close-lightbox"
  document.querySelectorAll('.close-lightbox').forEach(button => {
    button.addEventListener('click', function () {
      Fancybox.close(); // Schließt die Lightbox
    });
  });
}

