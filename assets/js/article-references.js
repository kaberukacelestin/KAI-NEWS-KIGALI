document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.article-references-form').forEach(function (form) {
    const list = form.querySelector('.article-reference-list');
    const template = form.querySelector('.article-reference-template');
    const addButton = form.querySelector('.article-reference-add');

    addButton.addEventListener('click', function () {
      list.appendChild(template.content.firstElementChild.cloneNode(true));
      list.lastElementChild.querySelector('input').focus();
    });

    list.addEventListener('click', function (event) {
      const removeButton = event.target.closest('.article-reference-remove');
      if (removeButton) removeButton.closest('.article-reference-row').remove();
    });
  });
});
