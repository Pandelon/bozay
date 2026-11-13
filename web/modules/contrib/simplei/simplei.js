(function (Drupal, drupalSettings, once) {
  Drupal.behaviors.simplei = {
    attach(context, settings) {
      // Classic
      const [toolbar] = once(
        'simplei-toolbar',
        '#toolbar-administration #toolbar-bar',
        context,
      );
      if (toolbar) {
        toolbar.style.background = drupalSettings.simplei.background;

        const [toolbarAdmin] = once(
          'simplei-toolbar-admin',
          '#toolbar-item-administration',
          toolbar,
        );
        if (toolbarAdmin) {
          toolbarAdmin.textContent += ` (${drupalSettings.simplei.environment})`;
          toolbarAdmin.style.fontWeight = 'bold';
          toolbarAdmin.style.color = drupalSettings.simplei.color;
          toolbarAdmin.style.backgroundColor =
            drupalSettings.simplei.background;
          toolbarAdmin.style.backgroundImage = 'linear-gradient(rgba(0, 0, 0, 0.15))';
        }
      }

      // Gin admin theme
      const [ginToolbar] = once(
        'simplei-gin-toolbar',
        '#gin-toolbar-bar #toolbar-item-administration-tray .toolbar-icon-admin-toolbar-tools-help.toolbar-icon',
        context,
      );
      if (ginToolbar) {
        ginToolbar.classList.remove('toolbar-icon');
        ginToolbar.textContent = drupalSettings.simplei.environment;
        ginToolbar.style.height = '56px';
        ginToolbar.style.textIndent = 'unset';
        ginToolbar.style.paddingLeft = '20px';
        ginToolbar.style.paddingTop = '20px';
        ginToolbar.style.paddingBottom = '20px';
        ginToolbar.style.color = drupalSettings.simplei.color;
        ginToolbar.style.backgroundColor = drupalSettings.simplei.background;
        ginToolbar.style.fontWeight = 'bold';
        ginToolbar.style.fontSize = 'small';
      }
    },
  };
})(Drupal, drupalSettings, once);
