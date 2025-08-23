const STORAGE_KEY = 'inventoryItems';

function getItems() {
  const stored = localStorage.getItem(STORAGE_KEY);
  return stored ? JSON.parse(stored) : [];
}

function render(items) {
  const list = document.getElementById('public-list');
  const empty = document.getElementById('public-empty');
  list.innerHTML = '';
  if (items.length === 0) {
    empty.style.display = 'block';
  } else {
    empty.style.display = 'none';
    items.forEach(item => {
      const li = document.createElement('li');
      li.textContent = `${item.name} (x${item.qty})`;
      list.appendChild(li);
    });
  }
}

window.addEventListener('DOMContentLoaded', () => {
  render(getItems());
});
