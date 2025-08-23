const STORAGE_KEY = 'inventoryItems';

function getItems() {
  const stored = localStorage.getItem(STORAGE_KEY);
  return stored ? JSON.parse(stored) : [];
}

function saveItems(items) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
}

function render(items) {
  const list = document.getElementById('admin-list');
  const empty = document.getElementById('admin-empty');
  list.innerHTML = '';
  if (items.length === 0) {
    empty.style.display = 'block';
  } else {
    empty.style.display = 'none';
    items.forEach((item, index) => {
      const li = document.createElement('li');
      li.textContent = `${item.name} (x${item.qty})`;
      const btn = document.createElement('button');
      btn.textContent = 'Delete';
      btn.setAttribute('aria-label', `Delete item ${item.name}`);
      btn.dataset.index = index;
      btn.addEventListener('click', () => handleDelete(index, item.name));
      li.appendChild(btn);
      list.appendChild(li);
    });
  }
}

function handleDelete(index, name) {
  if (window.confirm(`Delete ${name}?`)) {
    const items = getItems();
    items.splice(index, 1);
    saveItems(items);
    render(items);
  }
}

function handleBulk() {
  const items = getItems();
  if (items.length === 0) return;
  if (window.confirm('Increase quantity for all items?')) {
    items.forEach(i => i.qty++);
    saveItems(items);
    render(items);
  }
}

function init() {
  const form = document.getElementById('item-form');
  const bulkBtn = document.getElementById('bulk-increment');
  form.addEventListener('submit', evt => {
    evt.preventDefault();
    const nameInput = document.getElementById('item-name');
    const qtyInput = document.getElementById('item-qty');
    const items = getItems();
    items.push({ name: nameInput.value, qty: Number(qtyInput.value) });
    saveItems(items);
    nameInput.value = '';
    qtyInput.value = '';
    render(items);
  });
  bulkBtn.addEventListener('click', handleBulk);
  render(getItems());
}

window.addEventListener('DOMContentLoaded', init);
