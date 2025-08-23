export function buildCSV(rows) {
  return rows
    .map(row =>
      row
        .map(cell => {
          const value = cell == null ? '' : String(cell);
          const escaped = value.replace(/"/g, '""');
          return `"${escaped}"`;
        })
        .join(',')
    )
    .join('\r\n');
}
