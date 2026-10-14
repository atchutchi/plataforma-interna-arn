/* Local newsroom controls. Position is represented by input order, not drag state. */
document.querySelectorAll('.arn-newsroom-admin [data-news-form]').forEach((form) => {
  form.querySelectorAll('[data-media-group]').forEach((group) => {
    const kind = group.dataset.mediaGroup;
    const limit = Number(group.dataset.limit);
    const holder = group.querySelector('[data-media-rows]');
    const add = group.querySelector('[data-add-media]');
    const fileName = kind === 'attachments' ? 'attachment_file' : `${kind}_file`;
    const description = kind === 'attachments' ? 'Designação' : 'Texto alternativo';
    const operation = form.querySelector('[name="operation"]').value;

    const renumber = () => {
      const rows = [...holder.querySelectorAll('[data-media-row]')];
      rows.forEach((row, index) => {
        const base = kind === 'featured' ? 'featured' : `${kind}[${index}]`;
        const prefix = `arn-news-${kind}-${index}-${operation}`;
        const errorKey = kind === 'featured' ? kind : `${kind}_${index}`;
        row.querySelector('[data-position]').textContent = String(index + 1);
        row.querySelector('input[type="hidden"]').name = `${base}[id]`;
        const text = row.querySelector('input[type="text"]');
        const file = row.querySelector('input[type="file"]');
        text.name = `${base}[description]`;
        text.id = `${prefix}-description`;
        file.name = kind === 'featured' ? fileName : `${fileName}[${index}]`;
        file.id = `${prefix}-file`;
        row.querySelector('[data-description-label]').htmlFor = text.id;
        row.querySelector('[data-file-label]').htmlFor = file.id;
        const textHelp = row.querySelector('.arn-newsroom-field small');
        textHelp.id = `arn-news-${errorKey}-help-${operation}`;
        const error = row.querySelector('.arn-newsroom-field-error');
        if (error) error.id = `arn-news-${errorKey}-error-${operation}`;
        text.setAttribute('aria-describedby', textHelp.id + (error ? ` ${error.id}` : ''));
        const fileHelp = row.querySelectorAll('.arn-newsroom-field small')[1];
        fileHelp.id = `${prefix}-file-help`;
        file.setAttribute('aria-describedby', fileHelp.id);
        row.querySelector('[data-move="up"]').disabled = index === 0;
        row.querySelector('[data-move="down"]').disabled = index === rows.length - 1;
      });
      add.hidden = rows.length >= limit;
    };

    add.addEventListener('click', () => {
      if (holder.querySelectorAll('[data-media-row]').length >= limit) return;
      const row = document.createElement('div');
      row.className = 'arn-newsroom-media-row';
      row.dataset.mediaRow = '';
      row.innerHTML = `<div class="arn-newsroom-media-row-head"><strong>Posição <span data-position></span></strong><div><button class="button" type="button" data-move="up" aria-label="Mover para cima">Subir</button><button class="button" type="button" data-move="down" aria-label="Mover para baixo">Descer</button><button class="button" type="button" data-remove-media>Retirar</button></div></div><input type="hidden" value=""><div class="arn-newsroom-field"><label data-description-label>${description}</label><input type="text" required maxlength="${kind === 'attachments' ? 160 : 300}"><small>Usa uma descrição compreensível para quem não vê o ficheiro.</small></div><div class="arn-newsroom-field"><label data-file-label>Seleccionar ficheiro</label><input type="file" required accept="${kind === 'attachments' ? '.pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg' : '.png,.jpg,.jpeg,image/png,image/jpeg'}"><small>Após erro, selecciona novamente o ficheiro novo.</small></div>`;
      holder.append(row);
      renumber();
      row.querySelector('input[type="text"]').focus();
    });
    group.addEventListener('click', (event) => {
      const button = event.target.closest('button');
      const row = button?.closest('[data-media-row]');
      if (!row || !holder.contains(row)) return;
      if (button.matches('[data-remove-media]')) {
        row.remove();
        renumber();
        add.focus();
      } else if (button.dataset.move === 'up' && row.previousElementSibling) {
        holder.insertBefore(row, row.previousElementSibling);
        renumber();
        button.focus();
      } else if (button.dataset.move === 'down' && row.nextElementSibling) {
        holder.insertBefore(row.nextElementSibling, row);
        renumber();
        button.focus();
      }
    });
    // PHP rows may be recovered after a validation error; normalize before the next POST.
    holder.querySelectorAll('[data-media-row]').forEach((row) => {
      row.querySelector('label[for*="-description"]').dataset.descriptionLabel = '';
      row.querySelector('label[for*="-file"]').dataset.fileLabel = '';
    });
    renumber();
  });
});
