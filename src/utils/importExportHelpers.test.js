const { parseCSV } = require('./importExportHelpers');

const defaultMappings = { csv: { title: ['name'], qty: ['qty'] } };

function createFile(contents) {
  return new File([contents], 'test.csv', { type: 'text/csv' });
}

test('parses quoted fields with embedded commas', async () => {
  const csv = 'name,qty\n"Widget, Large",5';
  const file = createFile(csv);
  const result = await parseCSV(file, defaultMappings);
  expect(result.data).toEqual([{ name: 'Widget, Large', qty: '5' }]);
  expect(result.mapping).toEqual({ title: 'name', qty: 'qty' });
});

test('handles CRLF line endings', async () => {
  const csv = 'name,qty\r\nWidget,5\r\n';
  const file = createFile(csv);
  const result = await parseCSV(file, defaultMappings);
  expect(result.data).toEqual([{ name: 'Widget', qty: '5' }]);
});
