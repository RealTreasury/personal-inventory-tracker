const { buildCSV } = require('./csvBuilder');

test('escapes quotes in titles', () => {
  const rows = [
    ['Title'],
    ['He said "Hello"']
  ];
  const csv = buildCSV(rows);
  expect(csv).toBe('"Title"\r\n"He said ""Hello"""');
});

test('handles titles with line breaks', () => {
  const rows = [
    ['Title'],
    ['Line1\nLine2']
  ];
  const csv = buildCSV(rows);
  expect(csv).toBe('"Title"\r\n"Line1\nLine2"');
});
