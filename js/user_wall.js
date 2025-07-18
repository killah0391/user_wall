((Drupal, once) => {
  'use strict';

  /**
   * Deaktiviert den Senden-Button, wenn sowohl das Textfeld leer ist als auch keine Bilder hochgeladen sind.
   * Diese Version ist robust gegenüber AJAX-Aktualisierungen.
   */
  Drupal.behaviors.toggleSubmitButtonState = {
    attach: function (context, settings) {
      // Finde das Formular-Element, das stabil bleibt.
      const forms = once('user-wall-form-state', '.user-wall-post-form', context);

      forms.forEach(form => {
        const textarea = form.querySelector('textarea[name="message"]');
        const submitButton = form.querySelector('.form-submit.send-button');

        // Stelle sicher, dass die Hauptelemente vorhanden sind.
        if (!textarea || !submitButton) {
          return;
        }

        const checkFormState = () => {
          // Die Abfrage nach dem Bild-Widget erfolgt innerhalb der Funktion,
          // um sicherzustellen, dass immer die aktuellste Version nach einem AJAX-Aufruf verwendet wird.
          const imageWidget = form.querySelector('.image-widget');

          const hasText = textarea.value.trim() !== '';
          // Prüfe sicher, ob das Widget und ein Bild darin existieren.
          const hasImages = imageWidget ? imageWidget.querySelector('.image-preview img') !== null : false;

          // Deaktiviere den Button nur, wenn BEIDES (Text und Bilder) fehlt.
          submitButton.disabled = !hasText && !hasImages;
        };

        // Event-Listener für das Textfeld.
        textarea.addEventListener('input', checkFormState);

        // Der MutationObserver wird an das stabile Formular-Element angehängt.
        // Er beobachtet alle Änderungen an den Kind-Elementen (z.B. durch AJAX).
        const observer = new MutationObserver(checkFormState);
        observer.observe(form, {
          childList: true, // Beobachte hinzugefügte/entfernte Knoten
          subtree: true    // Beobachte den gesamten Unterbaum
        });

        // Führe die Prüfung direkt beim Laden aus.
        checkFormState();
      });
    }
  };

  Drupal.behaviors.toggleCommentSubmitState = {
    attach: function (context, settings) {
      // Findet alle Kommentarformulare, die auf der Seite sind.
      const commentForms = once('user-wall-comment-form-state', '.user-wall-comment-form', context);
      commentForms.forEach(form => {
        const textarea = form.querySelector('textarea[name="comment"]');
        const submitButton = form.querySelector('.form-submit');

        if (!textarea || !submitButton) {
          return;
        }

        const checkCommentFormState = () => {
          const hasText = textarea.value.trim() !== '';
          submitButton.disabled = !hasText;
        };

        // Event-Listener für das Textfeld.
        textarea.addEventListener('input', checkCommentFormState);

        // Initialen Zustand beim Laden prüfen.
        checkCommentFormState();
      });
    }
  };

  /**
   * Macht alle Bild-Vorschauen in einem managed_file-Widget klickbar, um sie zur Löschung auszuwählen.
   */
  Drupal.behaviors.clickableImageSelection = {
    attach: function (context, settings) {
      const imageWidgets = once('clickable-image-widget', '.image-widget', context);

      imageWidgets.forEach(widget => {
        const allCheckboxes = widget.querySelectorAll('.image-widget-data input[type="checkbox"]');
        const allImages = widget.querySelectorAll('.image-preview img');
        // Finde den "Entfernen"-Button anhand seines data-drupal-selector Attributs.
        const removeButton = widget.querySelector('button[data-drupal-selector="edit-image-remove-button"]');

        if (!removeButton) {
          return;
        }

        /**
         * Prüft, ob mindestens eine Checkbox ausgewählt ist, und schaltet den Button entsprechend.
         */
        const checkRemoveButtonState = () => {
          // Prüfe, ob irgendeine der Checkboxen im Widget ausgewählt ist.
          const anyCheckboxSelected = Array.from(allCheckboxes).some(cb => cb.checked);
          // Deaktiviere den Button, wenn KEINE Checkbox ausgewählt ist.
          removeButton.disabled = !anyCheckboxSelected;
        };

        if (allImages.length > 0 && allImages.length === allCheckboxes.length) {
          allImages.forEach((imagePreview, index) => {
            const checkbox = allCheckboxes[index];
            const checkboxWrapper = checkbox.closest('.js-form-item');

            if (checkboxWrapper) {
              checkboxWrapper.style.display = 'none';
            }

            const updateStyle = () => {
              imagePreview.classList.toggle('is-selected', checkbox.checked);
            };

            // Füge den Event-Listener zum Bild hinzu.
            imagePreview.addEventListener('click', (e) => {
              checkbox.checked = !checkbox.checked;
              checkbox.dispatchEvent(new Event('change'));
              updateStyle();
              // Prüfe den Button-Status nach jeder Klick-Aktion.
              checkRemoveButtonState();
            });

            updateStyle();
          });
        }

        // Prüfe den initialen Zustand des Buttons, wenn die Seite geladen wird.
        checkRemoveButtonState();
      });
    }
  };

})(Drupal, once);
