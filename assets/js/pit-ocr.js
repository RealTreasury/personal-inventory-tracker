(function(){
    document.addEventListener('DOMContentLoaded', function(){
        var input = document.getElementById('pit-receipt');
        var results = document.getElementById('pit-ocr-results');
        var updateBtn = document.getElementById('pit-ocr-update');
        var lines = [];

        input && input.addEventListener('change', function(e){
            var file = e.target.files[0];
            if (!file) return;
            results.innerHTML = 'Processing...';
            Tesseract.recognize(file, 'eng').then(function(res){
                lines = res.data.text.split('\n').filter(function(l){ return l.trim().length; });
                results.innerHTML = '';
                lines.forEach(function(line, idx){
                    var div = document.createElement('div');
                    var select = document.createElement('select');
                    select.name = 'item';
                    for (var id in pitOCR.items){
                        var opt = document.createElement('option');
                        opt.value = id;
                        opt.textContent = pitOCR.items[id].name;
                        select.appendChild(opt);
                    }
                    var qty = document.createElement('input');
                    qty.type = 'number';
                    qty.step = '0.01';
                    qty.value = 1;
                    qty.dataset.id = idx;
                    div.appendChild(select);
                    div.appendChild(qty);
                    div.appendChild(document.createTextNode(' ' + line));
                    results.appendChild(div);
                });
            });
        });

        updateBtn && updateBtn.addEventListener('click', function(){
            var data = {};
            Array.prototype.forEach.call(results.querySelectorAll('div'), function(div){
                var select = div.querySelector('select');
                var qty = div.querySelector('input');
                if(select && qty){
                    var id = select.value;
                    data[id] = (data[id] || 0) + parseFloat(qty.value || 0);
                }
            });
            fetch(pitOCR.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'pit_update_items',
                    nonce: pitOCR.nonce,
                    items: JSON.stringify(data)
                })
            }).then(function(){
                alert('Inventory updated');
            });
        });
    });
})();
