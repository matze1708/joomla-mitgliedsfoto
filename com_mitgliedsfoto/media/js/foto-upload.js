/**
 * Foto-Oberfläche: Auswahl/Kamera, Zuschnitt im Format des Ausweis-Fotofelds (Cropper.js), Ausgabe 456x584 JPEG (600 dpi), Versand per fetch.
 * @license GNU General Public License version 2 or later
 */
(() => {
  'use strict';

  const T = (key, fallback) => {
    const text = window.Joomla && Joomla.Text ? Joomla.Text._(key) : '';
    return text && text !== key ? text : fallback;
  };

  const MAX_QUELLE = 40 * 1024 * 1024;

  const init = (root) => {
    const form = root.querySelector('.mf-form');
    const alertBox = root.querySelector('.mf-alert');
    const fertig = root.querySelector('.mf-fertig');
    const stage = root.querySelector('.mf-stage');
    const img = root.querySelector('.mf-bild');
    const vorschau = Array.from(root.querySelectorAll('.mf-vorschau, .mf-k-foto'));
    const absenden = root.querySelector('.mf-absenden');
    const breite = parseInt(root.dataset.breite, 10) || 456;
    const hoehe = parseInt(root.dataset.hoehe, 10) || 584;
    const minB = parseInt(root.dataset.minBreite, 10) || 0;
    const minH = parseInt(root.dataset.minHoehe, 10) || 0;
    let cropper = null;
    let objektUrl = null;
    let sendet = false;

    const fehler = (text) => {
      alertBox.textContent = text;
      alertBox.classList.remove('d-none');
      alertBox.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    };
    const fehlerWeg = () => alertBox.classList.add('d-none');

    const aufraeumen = () => {
      if (cropper) { cropper.destroy(); cropper = null; }
      root.querySelectorAll('.mf-k-foto').forEach((el) => el.classList.remove('mf-hat-bild'));
      if (objektUrl) { URL.revokeObjectURL(objektUrl); objektUrl = null; }
      absenden.disabled = true;
    };

    const laden = (datei) => {
      fehlerWeg();
      aufraeumen();

      if (!datei) { return; }
      if (!/^image\//.test(datei.type) || datei.size > MAX_QUELLE) {
        stage.classList.add('d-none');
        fehler(T('COM_MITGLIEDSFOTO_JS_KEIN_BILD', 'Bitte wählen Sie ein Foto aus.'));
        return;
      }

      objektUrl = URL.createObjectURL(datei);
      stage.classList.remove('d-none');
      img.onload = () => {
        cropper = new Cropper(img, {
          aspectRatio: breite / hoehe,
          viewMode: 1,
          dragMode: 'move',
          autoCropArea: 1,
          background: false,
          guides: false,
          preview: vorschau,
          ready() {
            root.querySelectorAll('.mf-k-foto').forEach((el) => el.classList.add('mf-hat-bild'));
            const d = cropper.getData(true);
            if (d.width < minB || d.height < minH) {
              fehler(T('COM_MITGLIEDSFOTO_JS_ZU_KLEIN', 'Das Foto hat eine zu geringe Auflösung.'));
              return;
            }
            absenden.disabled = false;
          },
          cropend() {
            const d = cropper.getData(true);
            if (d.width < minB || d.height < minH) {
              fehler(T('COM_MITGLIEDSFOTO_JS_ZU_KLEIN', 'Das Foto hat eine zu geringe Auflösung.'));
              absenden.disabled = true;
            } else {
              fehlerWeg();
              absenden.disabled = false;
            }
          },
        });
      };
      img.onerror = () => {
        stage.classList.add('d-none');
        fehler(T('COM_MITGLIEDSFOTO_JS_KEIN_BILD', 'Bitte wählen Sie ein Foto aus.'));
      };
      img.src = objektUrl;
    };

    root.querySelectorAll('.mf-file-kamera, .mf-file-datei').forEach((input) => {
      input.addEventListener('change', () => {
        laden(input.files && input.files[0]);
        input.value = ''; // gleiche Datei erneut wählbar (beliebig oft wiederholen)
      });
    });

    // Freie Erfassung: Eingaben (Vorname, Nachname, Nummer) live auf der Ausweis-Rückseite anzeigen
    root.querySelectorAll('[data-mf-feld]').forEach((ziel) => {
      const quelle = root.querySelector('#mf-' + ziel.dataset.mfFeld);
      if (quelle) {
        const setzen = () => { ziel.textContent = quelle.value; };
        quelle.addEventListener('input', setzen);
        setzen();
      }
    });

    root.querySelector('[data-mf="links"]').addEventListener('click', () => cropper && cropper.rotate(-90));
    root.querySelector('[data-mf="rechts"]').addEventListener('click', () => cropper && cropper.rotate(90));
    root.querySelector('[data-mf="zurueck"]').addEventListener('click', () => cropper && cropper.reset());

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      if (sendet) { return; }
      fehlerWeg();

      if (!cropper) { fehler(T('COM_MITGLIEDSFOTO_JS_KEIN_BILD', 'Bitte wählen Sie ein Foto aus.')); return; }
      if (!form.checkValidity()) { form.reportValidity(); return; }

      const d = cropper.getData(true);
      if (d.width < minB || d.height < minH) { fehler(T('COM_MITGLIEDSFOTO_JS_ZU_KLEIN', 'Das Foto hat eine zu geringe Auflösung.')); return; }

      const leinwand = cropper.getCroppedCanvas({
        width: breite, height: hoehe, fillColor: '#fff', imageSmoothingEnabled: true, imageSmoothingQuality: 'high',
      });

      sendet = true;
      absenden.disabled = true;
      const label = absenden.textContent;
      absenden.textContent = T('COM_MITGLIEDSFOTO_JS_SENDET', 'Wird gesendet …');

      leinwand.toBlob((blob) => {
        if (!blob) {
          fehler(T('COM_MITGLIEDSFOTO_JS_FEHLER', 'Fehler beim Senden.'));
          absenden.disabled = false;
          absenden.textContent = label;
          sendet = false;
          return;
        }
        const daten = new FormData(form);
        daten.append('foto', blob, 'foto.jpg');

        fetch(root.dataset.url + (root.dataset.url.includes('?') ? '&' : '?') + 'format=json', {
          method: 'POST', body: daten, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
          .then((r) => r.json().catch(() => null))
          .then((antwort) => {
            if (!antwort || !antwort.ok) {
              throw new Error((antwort && antwort.message) || T('COM_MITGLIEDSFOTO_JS_FEHLER', 'Fehler beim Senden.'));
            }
            aufraeumen();
            if (antwort.redirect) { window.location.href = antwort.redirect; return; }
            form.classList.add('d-none');
            stage.classList.add('d-none');
            fertig.textContent = antwort.message;
            fertig.classList.remove('d-none');
            fertig.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
          })
          .catch((e) => {
            fehler(e.message || T('COM_MITGLIEDSFOTO_JS_FEHLER', 'Fehler beim Senden.'));
            absenden.disabled = false;
            absenden.textContent = label;
            sendet = false;
          });
      }, 'image/jpeg', 0.9);
    });
  };

  const start = () => document.querySelectorAll('.mf-uploader').forEach(init);
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
})();
