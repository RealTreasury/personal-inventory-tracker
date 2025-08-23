(function(){
    const fileInput = document.getElementById('pit-ocr-file');
    const tableBody = document.querySelector('#pit-ocr-results tbody');
    const form = document.getElementById('pit-ocr-results').closest('form');

    fileInput.addEventListener('change', function(){
        const file = this.files[0];
        if (!file) {
            return;
        }
        Tesseract.recognize(file, 'eng').then(result => {
            const lines = result.data.text.split('\n').filter(line => line.trim().length);
            tableBody.innerHTML = '';
            lines.forEach((line, index) => {
                const tr = document.createElement('tr');
                const tdLine = document.createElement('td');
                tdLine.textContent = line;
                tr.appendChild(tdLine);

                const tdSelect = document.createElement('td');
                const select = document.createElement('select');
                pitItems.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.name;
                    select.appendChild(opt);
                });
                select.name = 'line[' + index + '][id]';
                tdSelect.appendChild(select);
                tr.appendChild(tdSelect);

                const tdQty = document.createElement('td');
                const qty = document.createElement('input');
                qty.type = 'number';
                qty.name = 'line[' + index + '][qty]';
                qty.value = 1;
                tdQty.appendChild(qty);
                tr.appendChild(tdQty);

                tableBody.appendChild(tr);
            });
        });
    });

    form.addEventListener('submit', function(){
        const updates = [];
        tableBody.querySelectorAll('tr').forEach(row => {
            const id = row.querySelector('select').value;
            const qty = row.querySelector('input[type="number"]').value;
            updates.push({ id: id, qty: qty });
        });
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'pit_ocr_updates';
        input.value = JSON.stringify(updates);
        form.appendChild(input);
    });
})();
