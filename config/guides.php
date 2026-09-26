<?php

/*
|--------------------------------------------------------------------------
| Section guides
|--------------------------------------------------------------------------
|
| What each section is for, what its main buttons do, and the one thing
| people get wrong. Read by resources/views/partials/guide.blade.php, which
| the layout includes once — so a section gets its guide by being listed
| here, with no change to its own template.
|
| Keyed by the first URL segment. Both languages sit side by side rather
| than going through lang/tl.json: these are paragraphs, not labels, and an
| edit that changes one should have the other in front of it.
|
| Keep it to three lines. Anything longer stops being read.
|
*/

return [

    'dashboard' => [
        'en' => ['title' => 'Dashboard', 'lines' => [
            'The tiles read today: who is active, who is present, who is still timed in, and what this week has cost so far.',
            'Live Attendance lists today\'s time-ins as the kiosks send them. Project Sites shows each site\'s range and each kiosk: green inside its site\'s range, red outside. Sites are pinned on the Sites page.',
            'The figures are not entered here. Each comes from another section, so a wrong number is corrected where it was recorded.',
        ]],
        'tl' => ['title' => 'Dashboard', 'lines' => [
            'Ang mga tile ay para sa ngayong araw: sino ang aktibo, sino ang present, sino ang naka-time in pa, at magkano na ang gastos ngayong linggo.',
            'Nasa Live Attendance ang mga nag-time in ngayong araw, habang ipinapadala ng kiosk. Nasa Project Sites ang range ng bawat site at ang bawat kiosk: berde kung nasa loob ng range ng site nito, pula kung nasa labas. Sa Sites page nilalagay ang pin ng site.',
            'Hindi dito inilalagay ang mga numero. Galing sa ibang section ang bawat isa, kaya doon itama ang mali.',
        ]],
    ],

    'attendance' => [
        'en' => ['title' => 'Attendance', 'lines' => [
            "Today's Attendance opens on who is working now; All lists the whole crew, including anyone not in yet or absent. History looks back over the days before.",
            'Click a row to see every scan behind the day. A time out nobody scanned is fixed there — use the shift\'s time or enter the real one.',
            'Attendance is what payroll counts, so correct it before you run payroll rather than after.',
        ]],
        'tl' => ['title' => 'Attendance', 'lines' => [
            "Nagbubukas ang Today's Attendance sa mga nagtatrabaho ngayon; nasa All ang buong crew, pati ang hindi pa pumapasok o absent. Ang History ang tumitingin sa mga nakaraang araw.",
            'I-click ang row para makita ang bawat scan ng araw. Doon din inaayos ang time out na hindi na-scan — gamitin ang oras ng shift o ilagay ang totoong oras.',
            'Ang attendance ang binibilang ng payroll, kaya itama ito bago magpatakbo ng payroll, hindi pagkatapos.',
        ]],
    ],

    'employees' => [
        'en' => ['title' => 'Employees', 'lines' => [
            'Register employee creates the record. A worker detected at the kiosk waits in Pending until the fingerprint is enrolled.',
            'Export to Excel downloads the active workforce. The gift on a row adds a bonus to this pay period.',
            'Remove asks to confirm first, and moves the worker to Removed, where they can be restored.',
        ]],
        'tl' => ['title' => 'Mga Empleyado', 'lines' => [
            'Ang Register employee ang gumagawa ng record. Ang worker na nakita ng kiosk ay nasa Pending hanggang ma-enroll ang fingerprint.',
            'Ang Export to Excel ay ida-download ang aktibong workforce. Ang regalo sa hanay ay nagdadagdag ng bonus sa pay period na ito.',
            'Magtatanong muna ang Remove, at ililipat ang worker sa Removed, kung saan puwede itong ibalik.',
        ]],
    ],

    'leave-advances' => [
        'en' => ['title' => 'Leave and Advances', 'lines' => [
            'File Leave records a request. It stays pending until somebody approves or rejects it, and payroll reads that decision.',
            'New Cash Advance records what was handed over; payroll collects the instalment each period, and Record payment covers anything paid back outside a run.',
            'Overtime is not filed here. Payroll counts it from attendance — the time past the shift\'s regular hours.',
        ]],
        'tl' => ['title' => 'Leave at Cash Advance', 'lines' => [
            'Ang File Leave ay nagre-record ng request. Pending ito hanggang may mag-approve o mag-reject, at ang desisyong iyon ang binabasa ng payroll.',
            'Ang New Cash Advance ang nagre-record ng ibinigay; kinokolekta ng payroll ang hulog kada panahon, at ang Record payment ay para sa binayaran sa labas ng run.',
            'Hindi dito fina-file ang overtime. Binibilang ito ng payroll mula sa attendance — ang oras na lampas sa regular hours ng shift.',
        ]],
    ],

    'sites' => [
        'en' => ['title' => 'Site Management', 'lines' => [
            'Add site takes a project name and a location: pick a place from the suggestions, type the address, or tap the map to drop a pin.',
            'The pin and its radius are where GPS attendance counts as on-site. A site with no pin is not checked, and its card says so.',
            'The pencil opens a site in the form to rename it, move its pin or change its radius. Removing a site moves its employees to Unassigned.',
        ]],
        'tl' => ['title' => 'Pamamahala ng Site', 'lines' => [
            'Ang Add site ay humihingi ng pangalan ng proyekto at lokasyon: pumili sa mga mungkahi, i-type ang address, o i-tap ang mapa para maglagay ng pin.',
            'Ang pin at ang radius nito ang hangganan kung saan tinatanggap ang GPS attendance bilang nasa site. Hindi sinusuri ang site na walang pin, at nakasulat iyon sa card nito.',
            'Binubuksan ng lapis ang site sa form para palitan ang pangalan, ilipat ang pin o baguhin ang radius. Kapag inalis ang site, mapupunta sa Unassigned ang mga empleyado nito.',
        ]],
    ],



    'payroll-records' => [
        'en' => ['title' => 'Payroll Records', 'lines' => [
            'The table breaks down pay for the period chosen above it.',
            'Weekly and Daily change how that period is grouped.',
            'Preview & Download opens the printable copy; Print or Save as PDF from there.',
        ]],
        'tl' => ['title' => 'Talaan ng Sahod', 'lines' => [
            'Hinahati-hati ng talahanayan ang sahod para sa panahong napili sa itaas.',
            'Ang Weekly at Daily ang nagpapalit kung paano pinagsama-sama ang panahong iyon.',
            'Ang Preview & Download ang nagbubukas ng kopyang paprint; doon ang Print o Save as PDF.',
        ]],
    ],

    'remittances' => [
        'en' => ['title' => 'Remittance Tracker', 'lines' => [
            'Each month\'s SSS, PhilHealth, Pag-IBIG and BIR remittances: the employee contributions payroll deducted, from the pay weeks that end in that month.',
            'Mark as paid records the reference number, the date, how it was paid and the receipt; a row opens each employee\'s share.',
            'There is no set due date: each month is brought up in the last week of the month after, and the red dot on Payroll Records says one is waiting. It can still be sent after that.',
        ]],
        'tl' => ['title' => 'Remittance Tracker', 'lines' => [
            'Ang SSS, PhilHealth, Pag-IBIG at BIR na ire-remit bawat buwan: ang kontribusyong ibinawas ng payroll, mula sa mga pay week na nagtatapos sa buwang iyon.',
            'Ang Mark as paid ang nagtatala ng reference number, petsa, paraan ng pagbayad at resibo; ang bawat hilera ang nagbubukas ng bahagi ng bawat empleyado.',
            'Walang takdang due date: ipinapaalala ang bawat buwan sa huling linggo ng kasunod na buwan, at ang pulang tuldok sa Payroll Records ang nagsasabing may naghihintay. Puwede pa rin itong ipasa pagkatapos noon.',
        ]],
    ],

    'payslips' => [
        'en' => ['title' => 'Payslips', 'lines' => [
            'Payslips appear here once their payroll run has been approved.',
            'View opens one. Print All prints the whole filtered list in a single pass.',
            'A missing payslip means its run has not been approved yet.',
        ]],
        'tl' => ['title' => 'Mga Payslip', 'lines' => [
            'Lumalabas dito ang payslip kapag naaprubahan na ang payroll run nito.',
            'Ang View ang nagbubukas ng isa. Ang Print All ang nagpi-print ng buong listahan nang sabay-sabay.',
            'Kapag may kulang na payslip, hindi pa naaprubahan ang run nito.',
        ]],
    ],

    'payroll-reports' => [
        'en' => ['title' => 'Payroll Reports', 'lines' => [
            'Pick a report across the top, then a period: a preset, or two dates (whole pay weeks are used). Site and name narrow it as you type.',
            'Every figure is the one Payroll Records shows for the same weeks; the totals compare with the period just before.',
            'Click a column to sort it. Export Excel downloads the table; Print / PDF prints the report alone.',
        ]],
        'tl' => ['title' => 'Mga Ulat sa Sahod', 'lines' => [
            'Pumili ng ulat sa itaas, saka ng panahon: isang preset, o dalawang petsa (buong pay week ang ginagamit). Ang site at pangalan ang nagpapaliit dito habang nagta-type.',
            'Ang bawat halaga ay kapareho ng ipinapakita ng Payroll Records sa parehong mga linggo; ikinukumpara ang kabuuan sa panahong nauna.',
            'I-click ang column para ayusin. Ang Export Excel ang nagda-download ng talaan; ang Print / PDF ang nagpi-print ng ulat lamang.',
        ]],
    ],

    'settings' => [
        'en' => ['title' => 'Payroll Settings', 'lines' => [
            'The tabs hold what payroll computes with: multipliers and deductions, attendance rules, labor types, holidays, bonus and vale.',
            'Save changes applies from the next payroll run. A period already paid is not rewritten.',
            'Add Labor Type creates a new rate that employees can then be put on.',
        ]],
        'tl' => ['title' => 'Settings ng Sahod', 'lines' => [
            'Nasa mga tab ang ginagamit sa pagkuwenta: multiplier at kaltas, patakaran sa attendance, uri ng paggawa, holiday, bonus at vale.',
            'Ang Save changes ay sa susunod na payroll run na iiral. Hindi na binabago ang panahong nabayaran na.',
            'Ang Add Labor Type ang gumagawa ng bagong rate na pwedeng ilagay sa mga empleyado.',
        ]],
    ],

    // English in both: the Analytics page is English-only, its guide included.
    'analytics' => [
        'en' => ['title' => 'Analytics and Insights', 'lines' => [
            'Every card and chart is counted from attendance and read off payroll — the records payslips are built from. Nothing is entered here.',
            'Date range, Site, Shift and Employee status redraw the whole page at once, and it refreshes itself every minute.',
            'If a number looks wrong, correct it in the section it came from and the chart follows.',
        ]],
        'tl' => ['title' => 'Analytics and Insights', 'lines' => [
            'Every card and chart is counted from attendance and read off payroll — the records payslips are built from. Nothing is entered here.',
            'Date range, Site, Shift and Employee status redraw the whole page at once, and it refreshes itself every minute.',
            'If a number looks wrong, correct it in the section it came from and the chart follows.',
        ]],
    ],

    'ai-assistant' => [
        'en' => ['title' => 'Jeyanco AI', 'lines' => [
            'Ask in plain language about payroll, attendance and the workforce.',
            'Prompts holds ready-made questions if you are not sure where to start.',
            'New Chat clears the thread and starts again.',
        ]],
        'tl' => ['title' => 'Jeyanco AI', 'lines' => [
            'Magtanong sa karaniwang salita tungkol sa sahod, attendance at mga manggagawa.',
            'Nasa Prompts ang mga handang tanong kung hindi ka sigurado kung saan magsisimula.',
            'Nililinis ng New Chat ang usapan at nagsisimula ulit.',
        ]],
    ],

    'device-monitoring' => [
        'en' => ['title' => 'Device Monitoring', 'lines' => [
            'Lists the devices that report in, and when each was last heard from.',
            'A device that has gone quiet has simply not reported recently.',
            'Nothing is edited here — it is a status view.',
        ]],
        'tl' => ['title' => 'Pagsubaybay sa Device', 'lines' => [
            'Nakalista ang mga device na nag-uulat, at kung kailan huling nakarinig sa bawat isa.',
            'Ang device na tahimik ay hindi lang kamakailan nag-ulat.',
            'Walang binabago dito — pantingin lang ito ng status.',
        ]],
    ],

    'users-roles' => [
        'en' => ['title' => 'Users and Roles', 'lines' => [
            'A role decides which sections a person is allowed to open.',
            'Save applies at once; the person sees the change the next time a page loads for them.',
            'Account Management is the other half of this — it is where the logins themselves are created.',
        ]],
        'tl' => ['title' => 'Mga User at Tungkulin', 'lines' => [
            'Ang tungkulin ang nagpapasya kung aling section ang pwedeng buksan ng isang tao.',
            'Agad iniiral ang Save; makikita ito ng tao sa susunod na pag-load ng page niya.',
            'Kabilang nito ang Account Management — doon ginagawa ang mismong mga login.',
        ]],
    ],

    'audit-logs' => [
        'en' => ['title' => 'Audit Logs', 'lines' => [
            'A record of what changed, who changed it, and when.',
            'Apply narrows the list by the filters above it; Reset clears them.',
            'Entries cannot be edited. That is the point of a log.',
        ]],
        'tl' => ['title' => 'Mga Audit Log', 'lines' => [
            'Talaan ng kung ano ang nabago, sino ang nagbago, at kailan.',
            'Ang Apply ang sumasala ayon sa filter sa itaas; ang Reset ang naglilinis.',
            'Hindi maaaring baguhin ang mga tala. Iyon ang silbi ng isang log.',
        ]],
    ],

    'system-settings' => [
        'en' => ['title' => 'System Settings', 'lines' => [
            'Company holds the name, tagline, address and logo used on payslips, exports and the sign-in page.',
            'Accounts & roles creates logins. Appearance sets the theme and language a screen opens on. Security sets the session and password rules.',
            'Save writes straight away, and the rest of the app follows on the next page load.',
        ]],
        'tl' => ['title' => 'Settings ng Sistema', 'lines' => [
            'Nasa Company ang pangalan, tagline, address at logo na ginagamit sa payslip, export at sa sign-in page.',
            'Ang Accounts & roles ang gumagawa ng login. Ang Appearance ang nagtatakda ng tema at wika. Ang Security ang para sa session at password.',
            'Agad nagsusulat ang Save, at susunod ang app sa susunod na pag-load ng page.',
        ]],
    ],

    'accounts' => [
        'en' => ['title' => 'Account Management', 'lines' => [
            'Create Account makes a login and gives it a role.',
            'On each row: the pencil edits, the person icon deactivates without deleting, and the bin removes the account for good.',
            'Your own row has only the pencil — you cannot deactivate or delete the account you are signed in with.',
        ]],
        'tl' => ['title' => 'Pamamahala ng Account', 'lines' => [
            'Ang Create Account ang gumagawa ng login at nagbibigay ng tungkulin.',
            'Sa bawat row: ang lapis ang nag-e-edit, ang icon ng tao ang nagde-deactivate nang hindi binubura, at ang basurahan ang tuluyang nag-aalis.',
            'Lapis lang ang nasa sarili mong row — hindi mo pwedeng i-deactivate o burahin ang account na ginagamit mo.',
        ]],
    ],

];
