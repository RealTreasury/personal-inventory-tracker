(function(){
  const data = window.PITAppData || {};
  const app = document.getElementById('pit-app');
  if(!app) return;
  let items = [];
  let search = '';
  let filter = '';
  const headers = ['ID','Name','Category','Quantity','Purchased'];

  function fetchItems(){
    fetch(data.restUrl + '/items')
      .then(r=>r.json())
      .then(res=>{ items = res; render(); });
  }

  function render(){
    app.innerHTML = '';
    const controls = document.createElement('div');
    controls.className = 'pit-controls';
    controls.innerHTML = `
      <input type="text" id="pit-search" placeholder="${data.i18n.search}" />
      <select id="pit-filter"><option value="">${data.i18n.allCategories}</option></select>
      <button id="pit-export">${data.i18n.export}</button>
      <button id="pit-scan">${data.i18n.scan}</button>
    `;
    app.appendChild(controls);

    const searchInput = controls.querySelector('#pit-search');
    searchInput.value = search;
    searchInput.addEventListener('input', e => { search = e.target.value.toLowerCase(); renderList(); });

    const filterSelect = controls.querySelector('#pit-filter');
    const categories = [...new Set(items.map(i=>i.category))].sort();
    categories.forEach(cat => {
      const opt = document.createElement('option');
      opt.value = cat;
      opt.textContent = cat;
      if(cat===filter) opt.selected = true;
      filterSelect.appendChild(opt);
    });
    filterSelect.addEventListener('change', e => { filter = e.target.value; renderList(); });

    controls.querySelector('#pit-export').addEventListener('click', exportCSV);
    controls.querySelector('#pit-scan').addEventListener('click', scanReceipt);

    const list = document.createElement('div');
    list.id = 'pit-list';
    app.appendChild(list);

    const form = document.createElement('form');
    form.innerHTML = `
      <input type="text" id="pit-new-name" placeholder="${data.i18n.itemName}" required />
      <input type="text" id="pit-new-category" placeholder="${data.i18n.category}" />
      <input type="number" id="pit-new-quantity" value="1" min="0" />
      <button>${data.i18n.addItem}</button>
    `;
    form.addEventListener('submit', e => {
      e.preventDefault();
      addItem({
        name: form.querySelector('#pit-new-name').value,
        category: form.querySelector('#pit-new-category').value,
        quantity: parseInt(form.querySelector('#pit-new-quantity').value,10) || 0
      });
      form.reset();
    });
    app.appendChild(form);

    renderList();
  }

  function renderList(){
    const list = document.getElementById('pit-list');
    if(!list) return;
    list.innerHTML = '';
    let filtered = items.filter(i =>
      i.name.toLowerCase().includes(search) &&
      (!filter || i.category === filter)
    );
    filtered.forEach(item => {
      const row = document.createElement('div');
      row.className = 'pit-item' + (item.purchased ? ' purchased' : '');
      row.innerHTML = `
        <span class="pit-name">${item.name}</span>
        <input type="number" min="0" value="${item.quantity}" />
        <span>${item.category || ''}</span>
        <input type="checkbox" ${item.purchased ? 'checked' : ''} />
        <button class="pit-delete">${data.i18n.delete}</button>
      `;
      const qty = row.querySelector('input[type="number"]');
      qty.addEventListener('change', e => updateItem(item.id, {quantity: parseInt(e.target.value,10) || 0}));
      const chk = row.querySelector('input[type="checkbox"]');
      chk.addEventListener('change', e => updateItem(item.id, {purchased: e.target.checked}));
      row.querySelector('.pit-delete').addEventListener('click', () => deleteItem(item.id));
      list.appendChild(row);
    });
  }

  function addItem(payload){
    fetch(data.restUrl + '/items', {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-WP-Nonce': data.nonce},
      body: JSON.stringify(payload)
    }).then(r=>r.json()).then(res => { items.push(res); renderList(); });
  }

  function updateItem(id, payload){
    fetch(data.restUrl + '/items/' + id, {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-WP-Nonce': data.nonce},
      body: JSON.stringify(payload)
    }).then(r=>r.json()).then(res => {
      const idx = items.findIndex(i=>i.id==id);
      if(idx>-1) items[idx]=res;
      renderList();
    });
  }

  function deleteItem(id){
    fetch(data.restUrl + '/items/' + id, {
      method: 'DELETE',
      headers: {'X-WP-Nonce': data.nonce}
    }).then(()=>{
      items = items.filter(i=>i.id!=id);
      renderList();
    });
  }

  function exportCSV(){
    const rows = [headers.join(',')].concat(items.map(i=>[i.id,i.name,i.category,i.quantity,i.purchased].join(',')));
    const blob = new Blob([rows.join('\n')],{type:'text/csv'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'inventory.csv';
    a.click();
    URL.revokeObjectURL(url);
  }

  function scanReceipt(){
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.addEventListener('change', e => {
      const file = e.target.files[0];
      if(!file) return;
      Tesseract.recognize(file,'eng').then(result => {
        result.data.text.split('\n').forEach(line => {
          const name = line.trim();
          if(name){
            addItem({name:name, quantity:1, category:''});
          }
        });
      });
    });
    input.click();
  }

  fetchItems();
})();
