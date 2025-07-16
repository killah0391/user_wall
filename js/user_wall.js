((Drupal, once) => {
  'use strict';

  /**
   * Makes all image previews in a managed_file widget clickable to select for deletion.
   */
  Drupal.behaviors.clickableImageSelection = {
    attach: function (context, settings) {
      // Find each main image widget container.
      const imageWidgets = once('clickable-image-widget', '.image-widget', context);

      imageWidgets.forEach(widget => {
        // Find ALL images and ALL checkboxes within the widget.
        const allImages = widget.querySelectorAll('.image-preview img');
        const allCheckboxes = widget.querySelectorAll('.image-widget-data input[type="checkbox"]');

        // Only proceed if we have images and the number of images matches the number of checkboxes.
        if (allImages.length > 0 && allImages.length === allCheckboxes.length) {

          // Loop through each image and pair it with the checkbox at the same index.
          allImages.forEach((imagePreview, index) => {
            const checkbox = allCheckboxes[index];

            // Hide the checkbox's parent container.
            const checkboxWrapper = checkbox.closest('.js-form-item');
            if (checkboxWrapper) {
              checkboxWrapper.style.display = 'none';
            }

            // Function to update the visual style based on checkbox state.
            const updateStyle = () => {
              if (checkbox.checked) {
                imagePreview.classList.add('is-selected');
              } else {
                imagePreview.classList.remove('is-selected');
              }
            };

            // Add click listener to the image.
            imagePreview.addEventListener('click', (e) => {
              checkbox.checked = !checkbox.checked;
              checkbox.dispatchEvent(new Event('change'));
              updateStyle();
            });

            // Set the initial style on page load.
            updateStyle();
          });
        }
      });
    }
  };

})(Drupal, once);
