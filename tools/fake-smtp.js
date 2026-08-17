#!/usr/bin/env node
/**
 * Codeface dev tool — fake SMTP server (plain, non-TLS) for testing the forgot-password
 * email flow without a real Gmail account. Speaks just enough SMTP (EHLO, AUTH LOGIN,
 * MAIL/RCPT/DATA, QUIT) and appends every message body to a capture file.
 *
 * Usage:
 *   node tools/fake-smtp.js /tmp/smtpbox.log 2525 &
 *   CODEFACE_SMTP_HOST=127.0.0.1 CODEFACE_SMTP_PORT=2525 CODEFACE_SMTP_SECURE=none \
 *   CODEFACE_SMTP_USER=demo CODEFACE_SMTP_PASS=demo php -S 127.0.0.1:8093 -t .
 */
'use strict';
const net = require('net');
const fs = require('fs');

const OUT  = process.argv[2] || '/tmp/smtpbox.log';
const PORT = +(process.argv[3] || 2525);

const server = net.createServer((sock) => {
  let buf = '', inData = false, data = '', authStage = 0;
  const send = (l) => sock.write(l + '\r\n');
  send('220 fake-smtp ready');

  sock.on('data', (chunk) => {
    if (inData) {
      data += chunk.toString();
      const end = data.indexOf('\r\n.\r\n');
      if (end >= 0) {
        fs.appendFileSync(OUT, '==== ' + new Date().toISOString() + ' ====\n' + data.slice(0, end) + '\n');
        data = ''; inData = false;
        send('250 OK queued');
      }
      return;
    }
    buf += chunk.toString();
    let i;
    while ((i = buf.indexOf('\r\n')) >= 0) {
      const line = buf.slice(0, i); buf = buf.slice(i + 2);
      const u = line.toUpperCase();
      if (u.startsWith('EHLO') || u.startsWith('HELO')) { sock.write('250-fake-smtp\r\n250 AUTH LOGIN\r\n'); }
      else if (u === 'AUTH LOGIN') { authStage = 1; send('334 ' + Buffer.from('Username:').toString('base64')); }
      else if (authStage === 1)    { authStage = 2; send('334 ' + Buffer.from('Password:').toString('base64')); }
      else if (authStage === 2)    { authStage = 0; send('235 2.7.0 Accepted'); }
      else if (u.startsWith('MAIL FROM:')) send('250 OK');
      else if (u.startsWith('RCPT TO:'))   send('250 OK');
      else if (u === 'DATA') { inData = true; data = ''; send('354 End data with <CR><LF>.<CR><LF>'); }
      else if (u === 'RSET') send('250 OK');
      else if (u === 'NOOP') send('250 OK');
      else if (u === 'QUIT') { send('221 Bye'); sock.end(); }
      else send('502 not implemented');
    }
  });
  sock.on('error', () => {});
});

server.listen(PORT, '127.0.0.1', () => console.log(`fake-smtp listening on 127.0.0.1:${PORT} → ${OUT}`));
