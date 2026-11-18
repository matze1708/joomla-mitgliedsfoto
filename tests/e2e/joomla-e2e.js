const {chromium}=require('playwright');const fs=require('fs');const {execSync}=require('child_process');
const B='http://127.0.0.1:8080';const SITE=B+'/component/mitgliedsfoto/foto';
const sql=q=>execSync(`mysql -uroot --socket=/run/mf.sock joomla -N -e "${q}"`).toString().trim();
let fail=0;const ok=(n,c)=>{console.log((c?'OK    ':'FEHLER')+' '+n);if(!c)fail++;};
(async()=>{
 const b=await chromium.launch();const ctx=await b.newContext({acceptDownloads:true});const p=await ctx.newPage();
 const errs=[];p.on('pageerror',e=>errs.push(e.message));
 // --- QR-Weg
 await p.goto(SITE);
 await p.fill('#mf-nr','9001');await p.fill('#mf-gb','18.05.1980');await Promise.all([p.waitForNavigation({waitUntil:'load'}),p.click('button[type=submit]')]);
 ok('QR: falsches Geburtsdatum -> generische Meldung',(await p.content()).includes('konnten nicht bestätigt werden'));
 await p.fill('#mf-nr',"9001' OR '1'='1");await p.fill('#mf-gb','17.05.1980');await Promise.all([p.waitForNavigation({waitUntil:'load'}),p.click('button[type=submit]')]);
 ok('QR: SQL-Injection-Versuch -> generische Meldung',(await p.content()).includes('konnten nicht bestätigt werden'));
 await p.fill('#mf-nr','9001');await p.fill('#mf-gb','17.05.1980');await Promise.all([p.waitForNavigation({waitUntil:'load'}),p.click('button[type=submit]')]);
 ok('QR: richtige Daten (UTC-Kalenderwert) -> persönlicher Link',/m=9001&t=[0-9a-f]{64}/.test(p.url()));
 const link=p.url();
 ok('Name angezeigt',(await p.content()).includes('Jörg Müller-Groß'));
 ok('noindex gesetzt',(await p.content()).includes('noindex'));
 // --- Angriffe auf den Upload-Endpunkt (mit gültigem CSRF-Token der Seite)
 const angriff=async(feld,blob,name,extra={})=>p.evaluate(async([feld,b64,name,extra])=>{
   const f=new FormData(document.querySelector('.mf-form'));f.set('bin_ich','1');f.set('einwilligung','1');
   for(const k in extra) f.set(k,extra[k]);
   f.append(feld,new Blob([Uint8Array.from(atob(b64),c=>c.charCodeAt(0))]),name);
   const r=await fetch(document.querySelector('.mf-uploader').dataset.url+'&format=json',{method:'POST',body:f});return r.text();
 },[feld,Buffer.from(blob).toString('base64'),name,extra]);
 let r=await angriff('foto','<?php system("id"); ?>','shell.php');
 ok('PHP-Datei abgelehnt: '+r.slice(0,80),r.includes('"ok":false'));
 r=await angriff('foto',Buffer.concat([Buffer.from([0xff,0xd8,0xff]),Buffer.from('<?php system("id"); ?>')]),'x.jpg');
 ok('JPEG-Magic + PHP abgelehnt',r.includes('"ok":false'));
 r=await angriff('foto',fs.readFileSync('quer.jpg'),'quer.jpg');
 ok('Querformat/zu groß abgelehnt',r.includes('"ok":false'));
 r=await angriff('foto',fs.readFileSync('foto.jpg'),'foto.jpg',{t:'0'.repeat(64)});
 ok('Falscher Token abgelehnt',r.includes('"ok":false')&&r.includes('ungültig'));
 r=await angriff('foto',fs.readFileSync('foto.jpg'),'foto.jpg',{m:'../../9001'});
 ok('Manipulierte Nummer abgelehnt',r.includes('"ok":false'));
 ok('Zwischenstand: noch keine Zeile angelegt',sql("SELECT COUNT(*) FROM jos_mitgliedsfoto")==='0');
 sql("DELETE FROM jos_mitgliedsfoto_versuche");
 // --- normaler Upload
 await p.goto(link);
 await p.check('#mf-binich');await p.setInputFiles('.mf-file-datei','quer.jpg');
 await p.waitForFunction(()=>!document.querySelector('.mf-absenden').disabled);
 await p.check('#mf-einwilligung');await p.click('.mf-absenden');
 await p.waitForSelector('.mf-fertig:not(.d-none)',{timeout:15000});
 ok('Upload erfolgreich',(await p.locator('.mf-fertig').textContent()).includes('Vielen Dank'));
 const row=sql("SELECT status,dateiname,mail_gesendet_am IS NOT NULL,erfasst_von IS NULL FROM jos_mitgliedsfoto WHERE mitgliedsnr='9001'");
 ok('DB-Zeile: '+row,row==='eingereicht\tMueller-Gross_Joerg_9001.jpg\t1\t1');
 const datei='/var/www/joomla/administrator/components/com_mitgliedsfoto/fotos/Mueller-Gross_Joerg_9001.jpg';
 ok('Datei gespeichert (JPEG)',fs.existsSync(datei)&&fs.readFileSync(datei).slice(0,3).equals(Buffer.from([0xff,0xd8,0xff])));
 const mails=fs.readdirSync(process.env.MAILS).map(f=>fs.readFileSync(process.env.MAILS+'/'+f,'utf8'));
 const m=mails.find(x=>x.includes('FOTO|9001'))||'';
 ok('Mail: Betreff FOTO|9001',/Subject: FOTO\|9001/.test(m));
 ok('Mail: Body-Zeilen',m.includes('Mitgliedsnummer: 9001')&&m.includes('Nachname: M')&&m.includes('Quelle: web'));
 ok('Mail: Anhang',m.includes('Mueller-Gross_Joerg_9001.jpg')&&m.includes('image/jpeg'));
 ok('Mail: Empfänger',m.includes('To: ausweisfoto@example.org'));
 await p.goto(link);
 ok('Erneut öffnen -> bereits eingereicht',(await p.content()).includes('bereits eingereicht'));
 await p.goto(SITE+'?m=9001&t='+'a'.repeat(64));
 ok('Falscher Token -> neutrale Meldung',(await p.content()).includes('ungültig oder abgelaufen'));
 await p.goto(SITE+'?m=9999&t='+'a'.repeat(64));
 ok('Unbekannte Nr -> gleiche Meldung',(await p.content()).includes('ungültig oder abgelaufen'));
 await p.goto(SITE+'?m=<script>alert(1)</script>&t=x');
 ok('XSS im Parameter nicht reflektiert',!(await p.content()).includes('<script>alert(1)'));
 // --- Rate-Limit
 sql("DELETE FROM jos_mitgliedsfoto_versuche");
 for(let i=0;i<6;i++){await p.goto(SITE);await p.fill('#mf-nr','9002');await p.fill('#mf-gb','01.01.2000');await Promise.all([p.waitForNavigation({waitUntil:'load'}),p.click('button[type=submit]')]);}
 ok('Rate-Limit nach 5 Fehlversuchen',(await p.content()).includes('Zu viele Versuche'));
 await p.goto(SITE);await p.fill('#mf-nr','9002');await p.fill('#mf-gb','02.01.2012');await Promise.all([p.waitForNavigation({waitUntil:'load'}),p.click('button[type=submit]')]);
 ok('Gesperrt auch mit richtigen Daten',!p.url().includes('&t='));
 ok('IP nur als Hash gespeichert',!sql("SELECT GROUP_CONCAT(ip_hash) FROM jos_mitgliedsfoto_versuche").includes('127.0.0.1'));
 sql("DELETE FROM jos_mitgliedsfoto_versuche");
 // --- Backend
 const a=await ctx.newPage();a.on('pageerror',e=>errs.push('admin: '+a.url()+' '+e.stack.split('\n').slice(0,3).join(' | ')));
 await a.goto(B+'/administrator/index.php');
 await a.fill('#mod-login-username','admin');await a.fill('#mod-login-password',(process.env.JOOMLA_ADMIN_PW||'Test-Passwort-123!'));await a.click('#btn-login-submit');
 await a.goto(B+'/administrator/index.php?option=com_mitgliedsfoto');
 const liste=await a.content();
 ok('Liste: Jörg eingereicht mit Vorschau',liste.includes('Müller-Groß')&&liste.includes('task=foto.bild&amp;nr=9001'));
 ok('Liste: Anna offen + Foto erfassen',liste.includes('Schmidt')&&liste.includes('view=erfassen&amp;nr=9002'));
 ok('Liste: gesperrtes Konto (Karl) enthalten',liste.includes('Gesperrt'));
 ok('Liste: Konto ohne Nummer nicht enthalten',!liste.includes('Ohne'));
 ok('Liste: Eintritt angezeigt',liste.includes('2010'));
 ok('Filter Austritt: ausgetreten (9004) nicht in Liste',!liste.includes('>9004<'));
 ok('Filter Austritt: Austritt in Zukunft (9005) in Liste',liste.includes('>9005<'));
 ok('Filter Vereinsmitglied=1: Nichtmitglied (9006) nicht in Liste',!liste.includes('>9006<'));
 ok('Name aus Konto geteilt (Vorname Jörg / Nachname Müller-Groß)',/Müller-Groß<\/th>\s*<td>Jörg</.test(liste));
 ok('Warnung Webroot-Ordner',liste.includes('innerhalb des Webroots'));
 const bild=await a.request.get(B+'/administrator/index.php?option=com_mitgliedsfoto&task=foto.bild&nr=9001');
 ok('Bild-Auslieferung: JPEG + CSP + nosniff',bild.headers()['content-type']==='image/jpeg'&&(bild.headers()['content-security-policy']||'').includes('sandbox')&&bild.headers()['x-content-type-options']==='nosniff');
 const anon=await (await chromium.launch()).newContext();const an=await anon.request.get(B+'/administrator/index.php?option=com_mitgliedsfoto&task=foto.bild&nr=9001');
 ok('Bild ohne Login nicht abrufbar',!(an.headers()['content-type']||'').includes('image/jpeg'));
 // Suche + Filter
 await a.fill('#filter_search','Schmidt');await Promise.all([a.waitForNavigation(),a.keyboard.press('Enter')]);
 ok('Suche',(await a.content()).includes('Schmidt')&&!(await a.content()).includes('Müller-Groß'));
 await Promise.all([a.waitForNavigation(),a.click('.js-stools-btn-clear')]);
 // Optionen öffnen + speichern
 await a.goto(B+'/administrator/index.php?option=com_config&view=component&component=com_mitgliedsfoto');
 ok('Optionen: Benutzerfelder in Auswahl',(await a.content()).includes('Mitgliedsnummer (mitgliedsnummer, text)'));
 await Promise.all([a.waitForNavigation(),a.click('button.button-save')]);
 ok('Optionen speichern ohne Fehler',(await a.content()).includes('Configuration saved')||(await a.content()).includes('gespeichert'));
 ok('Schlüssel nach Speichern unverändert',sql("SELECT JSON_VALUE(params,'$.schluessel') FROM jos_extensions WHERE element='com_mitgliedsfoto'")==='aac9b9961c2cd896c45515ac729ffe7eccba4fea0c3523576847596ff8871bde');
 // Vorstandserfassung
 await a.goto(B+'/administrator/index.php?option=com_mitgliedsfoto&view=erfassen&nr=9002');
 await a.setInputFiles('.mf-file-kamera','quer.jpg');
 await a.waitForFunction(()=>!document.querySelector('.mf-absenden').disabled);
 await a.check('#mf-einwilligung');await a.click('.mf-absenden');
 await a.waitForURL(/view=mitglieder/,{timeout:15000,waitUntil:'load'});
 ok('Erfassen -> zurück zur Liste mit Meldung',(await a.content()).includes('wurde gespeichert'));
 ok('Status vorstand + erfasst_von',sql("SELECT CONCAT(status,'/',erfasst_von) FROM jos_mitgliedsfoto WHERE mitgliedsnr='9002'").startsWith('vorstand/'));
 // Export
 const [dl]=await Promise.all([a.waitForEvent('download'),a.evaluate(()=>Joomla.submitbutton('foto.export'))]);
 const csv=fs.readFileSync(await dl.path(),'utf8');
 ok('CSV-Kopf',csv.replace(/^﻿/,'').startsWith('Nr;Vorname;Nachname;E-Mail;Link'));
 const z=csv.split(/\r?\n/).find(l=>l.startsWith('9003'));
 ok('CSV enthält gesperrtes Konto 9003 mit Link',!!z&&/t=[0-9a-f]{64}/.test(z));
 await a.goto(B+'/administrator/index.php?option=com_mitgliedsfoto');
 const [dl2]=await Promise.all([a.waitForEvent('download'),a.evaluate(()=>Joomla.submitbutton('foto.exportOffene'))]);
 const csv2=fs.readFileSync(await dl2.path(),'utf8');
 ok('Export nur offene: ohne 9001/9002, mit 9003',!csv2.includes('\n9001;')&&!csv2.includes('\n9002;')&&csv2.includes('\n9003;'));
 // Link aus CSV für gesperrtes Konto funktioniert (ohne Login)
 await p.goto(z.split(';')[4].replace(/"/g,''));
 ok('Link aus CSV (Karl, gesperrtes Konto) -> Formular',(await p.content()).includes('Karl Gesperrt'));
 // Mail erneut, Zurücksetzen, Löschen
 const n0=fs.readdirSync(process.env.MAILS).length;
 await a.goto(B+'/administrator/index.php?option=com_mitgliedsfoto');
 await a.check('input[name="cid[]"][value="9001"]');await Promise.all([a.waitForNavigation({waitUntil:'load'}),a.evaluate(()=>Joomla.submitbutton('foto.mailsenden'))]);
 ok('Mail erneut senden',(await a.content()).includes('1 Mail gesendet')&&fs.readdirSync(process.env.MAILS).length===n0+1);
 await a.check('input[name="cid[]"][value="9002"]');await Promise.all([a.waitForNavigation({waitUntil:'load'}),a.evaluate(()=>Joomla.submitbutton('foto.loeschen'))]);
 ok('Foto löschen: Datei weg, Zeile bleibt',(await a.content()).includes('1 Foto gelöscht')&&sql("SELECT COUNT(*) FROM jos_mitgliedsfoto WHERE mitgliedsnr='9002' AND geloescht_am IS NOT NULL AND dateiname IS NULL")==='1');
 await a.check('input[name="cid[]"][value="9001"]');await Promise.all([a.waitForNavigation({waitUntil:'load'}),a.evaluate(()=>Joomla.submitbutton('foto.zuruecksetzen'))]);
 ok('Zurücksetzen: Zeile+Datei weg',(await a.content()).includes('zurückgesetzt')&&sql("SELECT COUNT(*) FROM jos_mitgliedsfoto WHERE mitgliedsnr='9001'")==='0'&&!fs.existsSync(datei));
 const csrf=await a.request.post(B+'/administrator/index.php?option=com_mitgliedsfoto&task=foto.zuruecksetzen',{form:{'cid[]':'9002'}});
 ok('Zurücksetzen ohne CSRF-Token abgelehnt',sql("SELECT COUNT(*) FROM jos_mitgliedsfoto WHERE mitgliedsnr='9002'")==='1');
 await a.screenshot({path:'admin-liste.png',fullPage:true});
 await p.goto(link);await p.setInputFiles('.mf-file-datei','quer.jpg');await p.waitForTimeout(800);await p.screenshot({path:'site-form.png',fullPage:true});
 const eigene=errs.filter(e=>!e.includes('guidedtours'));ok('Keine JS-Fehler der Komponente '+JSON.stringify(eigene),eigene.length===0);
 console.log(fail?`\n${fail} FEHLER`:'\nAlle E2E-Tests bestanden');await b.close();process.exit(0);
})().catch(e=>{console.error(e);process.exit(1);});
