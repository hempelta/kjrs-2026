import {ready} from "./Utility/Ready";

import * as bootstrap from 'bootstrap';

import { initPopovers } from './Bootstrap5/Popover';
// import { initOffcanvas } from './Bootstrap5/Offcanvas';
import { initVideoPlayer } from './Components/Video/initVideoPlayer';

// import { initNavbarAdvanced } from '@sitekit-presets/Bootstrap5/Partials/Menus/Main/Advanced/Index.js';
// import { initLanguageMenuDropdown } from '@sitekit-presets/Bootstrap5/Partials/Menus/Language/Dropdown/Index.js';
// import { initColorModesDropdown } from '@sitekit-presets/Bootstrap5/Partials/Features/ColorModes/Default/Index.js';

ready(function () {
  initPopovers();
  initVideoPlayer();
  // initOffcanvas();
  // initNavbarAdvanced();
  // initLanguageMenuDropdown();
  // initColorModesDropdown();
});

// --- HMR: accept updates to avoid "Cannot apply update" ---
if (import.meta && import.meta.webpackHot) {
  import.meta.webpackHot.accept();

  // Optional (Cleanup-Beispiel, falls du später doppelte Listener bemerkst):
  // import.meta.webpackHot.dispose(() => {
  //   Fancybox.destroy(); // nur wenn du wirklich aufräumen willst
  // });
}
