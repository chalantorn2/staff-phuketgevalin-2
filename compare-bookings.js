const fs = require('fs');

const old = JSON.parse(fs.readFileSync('./bookings_from_overseas.json', 'utf8'));
const sql = fs.readFileSync('./bookings 271025.sql', 'utf8');
const missing = old.filter(b => !sql.includes(b.reference));
const found = old.filter(b => sql.includes(b.reference));

console.log('=== สรุปการซิง ===\n');
console.log('ระบบเก่า (Total): ' + old.length + ' bookings');
console.log('ซิงได้: ' + found.length + ' bookings');
console.log('หายไป: ' + missing.length + ' bookings');
console.log('');

console.log('=== BOOKINGS ที่หายไป (' + missing.length + ' bookings) ===\n');
console.log('ตาม Status:');
const confirmedMissing = missing.filter(b => b.status === 'Confirmed');
const cancelledMissing = missing.filter(b => b.status === 'Cancelled');
console.log('  Confirmed: ' + confirmedMissing.length + ' bookings');
console.log('  Cancelled: ' + cancelledMissing.length + ' bookings');

console.log('\nตาม Booking Reference Prefix:');
const prefixes = {};
missing.forEach(b => {
  const prefix = b.reference.split('-')[0];
  prefixes[prefix] = (prefixes[prefix] || 0) + 1;
});
Object.entries(prefixes).sort((a,b) => b[1] - a[1]).forEach(([k,v]) =>
  console.log('  ' + k + ': ' + v + ' bookings')
);

console.log('\n=== รายละเอียด Bookings ที่หายไป ===\n');
missing.forEach(b => {
  console.log(b.reference + ' - ' + b.leadname);
  console.log('  Status: ' + b.status);
  console.log('  Date Booked: ' + b.date_booked);
  console.log('  Arriving: ' + b.arriving);
  console.log('');
});

console.log('\n=== Bookings ที่ซิงได้ (' + found.length + ' bookings) ===\n');
found.forEach(b => {
  console.log(b.reference + ' - ' + b.status);
});
