const el = document.getElementById('pit-shopping-list');

if (el) {
  el.textContent = 'Loading...';
  fetch(`${window.pitApp?.restUrl}shopping-list`, {
    headers: { 'X-WP-Nonce': window.pitApp?.nonce },
  })
    .then(res => res.json())
    .then(items => {
      const list = document.createElement('ul');
      items.forEach(item => {
        const li = document.createElement('li');
        li.textContent = `${item.name} (${item.qty})`;
        list.appendChild(li);
      });
      el.textContent = '';
      el.appendChild(list);
    })
    .catch(() => {
      el.textContent = 'Error loading list';
    });
}
