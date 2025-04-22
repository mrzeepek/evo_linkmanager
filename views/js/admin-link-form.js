/**
 * Link Form Management JavaScript
 *
 * @author    Evolutive Group
 * @copyright 2025 Evolutive Group
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

document.addEventListener('DOMContentLoaded', function() {
  // Toggle fields based on link type
  const handleLinkTypeToggle = function() {
    const linkTypeSelect = document.querySelector('select[name*="[link_type]"]');
    if (!linkTypeSelect) return;

    const urlField = document.getElementById('url-field');
    const cmsField = document.getElementById('cms-field');

    function updateVisibility() {
      const selectedValue = linkTypeSelect.value;

      if (selectedValue === 'cms') {
        urlField.style.display = 'none';
        cmsField.style.display = 'block';
      } else {
        urlField.style.display = 'block';
        cmsField.style.display = 'none';
      }
    }

    // Initial state
    updateVisibility();

    // Add listener for changes
    linkTypeSelect.addEventListener('change', updateVisibility);
  };

  // Help with identifier formatting - automatically format identifier field
  const handleIdentifierFormatting = function() {
    const identifierField = document.querySelector('input[name*="[identifier]"]');
    if (!identifierField) return;

    identifierField.addEventListener('input', function() {
      // Convert to lowercase and replace invalid characters
      this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '_');
    });
  };

  // Initialize all form handlers
  handleLinkTypeToggle();
  handleIdentifierFormatting();
});
