import * as bootstrap from 'bootstrap';

// Bootstrap5 Popover Initialisierung
const initPopovers = () => {
  const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
  const popoverList = [...popoverTriggerList].map(popoverTriggerEl =>
    new bootstrap.Popover(popoverTriggerEl)
  );
  return popoverList;
};

export {initPopovers};
