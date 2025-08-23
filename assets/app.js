(function(){
  document.addEventListener('DOMContentLoaded', function(){
    var app = document.getElementById('pit-app');
    if (!app || typeof pitApp === 'undefined') {
      return;
    }

    var items = [];
    var search = '';
    var show = 'all';

    var controls = document.createElement('div');
    var searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = pitApp.i18n.search;
    searchInput.addEventListener('input', function(e){
      search = e.target.value.toLowerCase();
      render();
    });

    var filterSelect = document.createElement('select');
    filterSelect.innerHTML = '<option value="all">'+pitApp.i18n.filterAll+'</option>'+
      '<option value="purchased">'+pitApp.i18n.filterPurchased+'</option>'+
      '<option value="needed">'+pitApp.i18n.filterNeeded+'</option>';
    filterSelect.addEventListener('change', function(e){
      show = e.target.value;
      render();
    });

    var exportBtn = document.createElement('button');
    exportBtn.textContent = pitApp.i18n.exportCsv;
    exportBtn.addEventListener('click', exportCsv);

    var receiptInput = document.createElement('input');
    receiptInput.type = 'file';
    receiptInput.accept = 'image/*';
    receiptInput.addEventListener('change', function(e){
      scanReceipt(e.target.files[0]);
    });

    controls.appendChild(searchInput);
    controls.appendChild(filterSelect);
    controls.appendChild(exportBtn);
    controls.appendChild(receiptInput);
    app.appendChild(controls);

    var addForm = document.createElement('form');
    var addName = document.createElement('input');
    addName.type = 'text';
    addName.placeholder = pitApp.i18n.addName;
    var addQty = document.createElement('input');
    addQty.type = 'number';
    addQty.value = 1;
    var addBtn = document.createElement('button');
    addBtn.textContent = pitApp.i18n.addItem;
    addForm.appendChild(addName);
    addForm.appendChild(addQty);
    addForm.appendChild(addBtn);
    addForm.addEventListener('submit', function(e){
      e.preventDefault();
      addItem(addName.value, addQty.value);
      addName.value = '';
      addQty.value = 1;
    });
    app.appendChild(addForm);

    var table = document.createElement('table');
    table.innerHTML = '<thead><tr><th>'+pitApp.i18n.item+'</th><th>'+pitApp.i18n.qty+'</th><th>'+pitApp.i18n.purchased+'</th><th>'+pitApp.i18n.actions+'</th></tr></thead><tbody></tbody>';
    var tbody = table.querySelector('tbody');
    app.appendChild(table);

    function fetchItems(){
      fetch(pitApp.restUrl + 'items', {
        headers: { 'X-WP-Nonce': pitApp.nonce }
      }).then(function(r){ return r.json(); }).then(function(data){
        items = data;
        render();
      });
    }

    function render(){
      tbody.innerHTML = '';
      items.filter(function(it){
        if (search && it.title.toLowerCase().indexOf(search) === -1) return false;
        if (show === 'purchased' && !it.purchased) return false;
        if (show === 'needed' && it.purchased) return false;
        return true;
      }).forEach(function(it){
        var tr = document.createElement('tr');
        var titleTd = document.createElement('td');
        titleTd.textContent = it.title;
        tr.appendChild(titleTd);

        var qtyTd = document.createElement('td');
        var minus = document.createElement('button');
        minus.type = 'button';
        minus.textContent = '-';
        minus.addEventListener('click', function(){ changeQty(it, -1); });
        var qtySpan = document.createElement('span');
        qtySpan.textContent = it.qty;
        var plus = document.createElement('button');
        plus.type = 'button';
        plus.textContent = '+';
        plus.addEventListener('click', function(){ changeQty(it, 1); });
        qtyTd.appendChild(minus);
        qtyTd.appendChild(qtySpan);
        qtyTd.appendChild(plus);
        tr.appendChild(qtyTd);

        var purchasedTd = document.createElement('td');
        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = it.purchased;
        checkbox.addEventListener('change', function(){ togglePurchased(it, checkbox.checked); });
        purchasedTd.appendChild(checkbox);
        tr.appendChild(purchasedTd);

        var actionsTd = document.createElement('td');
        actionsTd.className = 'actions';
        var del = document.createElement('button');
        del.type = 'button';
        del.textContent = 'x';
        del.addEventListener('click', function(){ deleteItem(it); });
        actionsTd.appendChild(del);
        tr.appendChild(actionsTd);

        tbody.appendChild(tr);
      });
    }

    function changeQty(it, delta){
      var newQty = Math.max(0, parseInt(it.qty, 10) + delta);
      updateItem(it.id, { qty: newQty }).then(function(updated){
        it.qty = updated.qty;
        render();
      });
    }

    function togglePurchased(it, val){
      updateItem(it.id, { purchased: val }).then(function(updated){
        it.purchased = updated.purchased;
        render();
      });
    }

    function updateItem(id, data){
      return fetch(pitApp.restUrl + 'items/' + id, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': pitApp.nonce
        },
        body: JSON.stringify(data)
      }).then(function(r){ return r.json(); });
    }

    function deleteItem(it){
      fetch(pitApp.restUrl + 'items/' + it.id, {
        method: 'DELETE',
        headers: { 'X-WP-Nonce': pitApp.nonce }
      }).then(function(){
        items = items.filter(function(i){ return i.id !== it.id; });
        render();
      });
    }

    function addItem(title, qty){
      if (!title) { return; }
      fetch(pitApp.restUrl + 'items', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': pitApp.nonce
        },
        body: JSON.stringify({ title: title, qty: parseInt(qty, 10) || 0 })
      }).then(function(r){ return r.json(); }).then(function(newItem){
        items.push(newItem);
        render();
      });
    }

    function exportCsv(){
      var csv = 'title,qty,purchased\n';
      items.forEach(function(it){
        csv += '"' + it.title.replace(/"/g,'""') + '",' + it.qty + ',' + it.purchased + '\n';
      });
      var blob = new Blob([csv], { type: 'text/csv' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'inventory.csv';
      a.click();
      URL.revokeObjectURL(a.href);
    }

    function scanReceipt(file){
      if (!file || typeof Tesseract === 'undefined') { return; }
      Tesseract.recognize(file, 'eng').then(function(res){
        res.data.lines.forEach(function(line){
          if (line.confidence >= 60) {
            addItem(line.text.trim(), 1);
          }
        });
      });
    }

    fetchItems();
  });
})();
