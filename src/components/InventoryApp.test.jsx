const { buildCSVContent } = require('./InventoryApp.jsx');

describe('buildCSVContent', () => {
  it('escapes quotes and line breaks in titles', () => {
    const items = [
      { title: 'Widget "A"', qty: 1, purchased: false },
      { title: 'Multi\nLine', qty: 2, purchased: true },
    ];
    const csv = buildCSVContent(items);
    const expected =
      'Title,Quantity,Purchased\n"Widget ""A""",1,No\n"Multi\nLine",2,Yes';
    expect(csv).toBe(expected);
  });
});
