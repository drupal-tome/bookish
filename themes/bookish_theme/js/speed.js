// This would normally go in bk_header.sfc in a <script> tag, but I don't
// want to add JS if people aren't using Bookish Speed.
document.addEventListener('bookish-speed-html', function () {
  var header = document.querySelector('.bk-header__name');
  if (!header) {
    return;
  }
  var newHeader = document.createElement(document.location.pathname === '/' ? 'h1' : 'a');
  newHeader.setAttribute('href', '/');
  newHeader.setAttribute('class', 'bk-header__name');
  newHeader.innerText = header.innerText;
  header.parentNode.replaceChild(newHeader, header);
  Drupal.attachBehaviors(newHeader.parentNode, window.drupalSettings);
});
