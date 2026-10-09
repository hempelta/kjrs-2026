import {ready} from "./Utility/Ready";

import * as bootstrap from 'bootstrap';

import {initPopovers} from './Bootstrap5/Popover';
import {initOffcanvas} from './Bootstrap5/Offcanvas';
import {initFancybox} from './Component/Fancybox';
import {AnchorForStickyNavbar} from './Utility/AnchorForStickyNavbar';

import {initNavbarAdvanced} from '@sitekit-components/Bootstrap5/Molecule/MainMenu/Advanced/initNavbarAdvanced';
import {
  initLanguageMenuDropdown
} from '@sitekit-components/Bootstrap5/Molecule/LanguageMenu/Dropdown/initLanguageMenuDropdown';
import {initColorModesDropdown} from '@sitekit-components/Bootstrap5/Molecule/ColorModes/initColorModesDropdown';
import {initVideoPlayer} from "@sitekit-components/Bootstrap5/Atom/Video/initVideoPlayer";

ready(function () {
  initPopovers();
  initOffcanvas();
  initFancybox();
  initVideoPlayer();
  AnchorForStickyNavbar();
  initNavbarAdvanced();
  initLanguageMenuDropdown();
  initColorModesDropdown();
});

// --- HMR: accept updates to avoid "Cannot apply update" ---
if (import.meta && import.meta.webpackHot) {
  import.meta.webpackHot.accept();

  // Optional (Cleanup-Beispiel, falls du später doppelte Listener bemerkst):
  // import.meta.webpackHot.dispose(() => {
  //   Fancybox.destroy(); // nur wenn du wirklich aufräumen willst
  // });
}
