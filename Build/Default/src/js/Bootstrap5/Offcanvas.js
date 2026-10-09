import * as bootstrap from 'bootstrap';

// Bootstrap5 Offcanvas Initialisierung
const initOffcanvas = () => {
  const offcanvasElementList = document.querySelectorAll('.offcanvas');
  const offcanvasList = [...offcanvasElementList].map(offcanvasEl =>
    new bootstrap.Offcanvas(offcanvasEl)
  );
  return offcanvasList;
};

export {initOffcanvas};
