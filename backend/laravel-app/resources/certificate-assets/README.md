# Assets del PDF

`document.css` define el documento A4 y se carga desde disco, sin red. `CertificateAssetResolver` solo permite una imagen PNG/JPG local cuyo nombre se haya configurado explícitamente en `certificate_pdf.approved_mark` después de aprobación y registro de procedencia. El valor actual es `null`, por lo que se usa cabecera tipográfica provisional.

DejaVu Sans llega con `dompdf/dompdf` 3.1.6 y se incrusta como subconjunto para conservar acentos y caracteres españoles. No se añadió ni copió una fuente externa al proyecto. [Dompdf documenta su fuente incluida](https://github.com/dompdf/dompdf/blob/master/README.md#about-fonts--character-encoding) y el [proyecto DejaVu publica la licencia](https://github.com/dejavu-fonts/dejavu-fonts/blob/master/LICENSE), derivada de Bitstream Vera, que permite reproducción y distribución con conservación de los avisos. Una futura tipografía institucional exige verificar su licencia de incrustación antes de adoptarla.

Los elementos oficiales faltantes están en `CERTIFICATE_ASSETS_REQUIRED.md` en la raíz del proyecto. No añadir aquí imágenes remotas, firmas o QR de prueba.
