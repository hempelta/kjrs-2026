/**
 * @param {Function} callback
 */
export function ready(callback) {
  if (document.readyState === 'complete') {
    // If everything is already loaded, we can call the callback immediately
    callback.call(null);
    return;
  }

  function clearListeners() {
    window.removeEventListener('load', delegate);
    document.removeEventListener('DOMContentLoaded', delegate);
  }

  function delegate() {
    clearListeners();
    callback.call(null);
  }

  // Otherwise, we make sure to be called, but allow to be called when dom is ready
  document.addEventListener('DOMContentLoaded', delegate);
  window.addEventListener('load', delegate);
}
