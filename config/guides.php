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
            'The four buttons at the top go straight to the work — register a worker, start a payroll run, open attendance or payroll records.',
            'Nothing is entered here. Every figure comes from another section, so a wrong number is corrected where it was recorded.',
        ]],
        'tl' => ['title' => 'Dashboard', 'lines' => [
            'Ang mga tile ay para sa ngayong araw: sino ang aktibo, sino ang present, sino ang naka-time in pa, at magkano na ang gastos ngayong linggo.',
            'Ang apat na buton sa itaas ay diretso sa trabaho — magrehistro ng manggagawa, magsimula ng payroll run, buksan ang attendance o payroll records.',
            'Walang inilalagay dito. Galing sa ibang section ang bawat numero, kaya doon itama ang mali.',
        ]],
    ],

    'attendance' => [
        'en' => ['title' => 'Attendance', 'lines' => [
            "Today's Attendance shows the current day's time in and out. History looks back over the days before it.",
            'Mark for Deletion turns on the checkboxes; Delete Selected then removes only what you ticked.',
            'Attendance is what payroll counts, so correct it before you run payroll rather than after.',
        ]],
        'tl' => ['title' => 'Attendance', 'lines' => [
            "Ang Today's Attendance ay ang time in at time out ngayong araw. Ang History ang tumitingin sa mga nakaraang araw.",
            'Binubuksan ng Mark for Deletion ang mga checkbox; ang Delete Selected na ang magtatanggal ng na-tsek mo lang.',
            'Ang attendance ang binibilang ng payroll, kaya itama ito bago magpatakbo ng payroll, hindi pagkatapos.',
        ]],
    ],

    'employees' => [
        'en' => ['title' => 'Employee Directory', 'lines' => [
            'Add Employee creates the record. The tabs above the list filter it: All, Regular, Contractual.',
            'Export downloads the list as you are looking at it, with whatever filters are applied.',
            'Delete asks to confirm first, and takes the employee out of every list that reads this directory.',
        ]],
        'tl' => ['title' => 'Direktoryo ng Empleyado', 'lines' => [
            'Ang Add Employee ang gumagawa ng record. Ang mga tab sa itaas ang sumasala: All, Regular, Contractual.',
            'Ang Export ay ida-download ang listahan gaya ng nakikita mo, kasama ang mga filter.',
            'Magtatanong muna ang Delete, at aalisin ang empleyado sa lahat ng listahang bumabasa sa direktoryong ito.',
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
            'Add Site creates one. The name is what shows everywhere a site is chosen.',
            'Removing a site reassigns its employees automatically, so nobody is left without one.',
            'Rename changes the label wherever the site appears.',
        ]],
        'tl' => ['title' => 'Pamamahala ng Site', 'lines' => [
            'Ang Add Site ang gumagawa ng bago. Ang pangalan ang lalabas saanman pumipili ng site.',
            'Kapag inalis ang isang site, awtomatikong nailipat ang mga empleyado nito, kaya walang maiiwan.',
            'Ang Rename ang nagpapalit ng pangalan saanman lumalabas ang site.',
        ]],
    ],

    'project-assignments' => [
        'en' => ['title' => 'Project Assignment', 'lines' => [
            'New Assignment puts an employee on a project for a period.',
            'End closes an assignment without deleting it, so the roster keeps its history.',
            'The counts show how many are assigned and how many are on the roster.',
        ]],
        'tl' => ['title' => 'Atas sa Proyekto', 'lines' => [
            'Ang New Assignment ang naglalagay ng empleyado sa isang proyekto sa loob ng takdang panahon.',
            'Ang End ang nagsasara ng atas nang hindi binubura, kaya nananatili ang kasaysayan sa roster.',
            'Ipinapakita ng bilang kung ilan ang naka-atas at ilan ang nasa roster.',
        ]],
    ],

    'payroll-processing' => [
        'en' => ['title' => 'Payroll Processing', 'lines' => [
            'New Payroll Run picks the period; Create & Calculate works the figures out straight away.',
            'Nothing is paid until you finalise, so Review is where you check before that happens.',
            'Approving a run is what makes its payslips appear in the Payslips section.',
        ]],
        'tl' => ['title' => 'Pagproseso ng Sahod', 'lines' => [
            'Ang New Payroll Run ang pumipili ng panahon; ang Create & Calculate ang agad kumukuwenta.',
            'Walang binabayaran hangga\'t hindi mo tinatapos, kaya sa Review ka muna tumingin.',
            'Ang pag-apruba ng run ang naglalabas ng payslip nito sa Payslips.',
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

    'payslips' => [
        'en' => ['title' => 'Payslips', 'lines' => [
            'Payslips appear here once their payroll run has been approved in Payroll Processing.',
            'View opens one. Print All prints the whole filtered list in a single pass.',
            'A missing payslip means its run has not been approved yet.',
        ]],
        'tl' => ['title' => 'Mga Payslip', 'lines' => [
            'Lumalabas dito ang payslip kapag naaprubahan na ang payroll run nito sa Payroll Processing.',
            'Ang View ang nagbubukas ng isa. Ang Print All ang nagpi-print ng buong listahan nang sabay-sabay.',
            'Kapag may kulang na payslip, hindi pa naaprubahan ang run nito.',
        ]],
    ],

    'payroll-reports' => [
        'en' => ['title' => 'Payroll Reports', 'lines' => [
            'Pick the report and the period, then Apply.',
            'Print produces the copy to hand over or to file.',
            'Reset clears the filters back to the default period.',
        ]],
        'tl' => ['title' => 'Mga Ulat sa Sahod', 'lines' => [
            'Piliin ang ulat at ang panahon, tapos Apply.',
            'Ang Print ang gumagawa ng kopyang ibibigay o itatago.',
            'Ang Reset ang nagbabalik ng filter sa karaniwang panahon.',
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

    'analytics' => [
        'en' => ['title' => 'Analytics and Insights', 'lines' => [
            'The charts read the same records as the rest of the system. Nothing is entered here.',
            'The figures follow the period and the filters chosen on the page.',
            'If a number looks wrong, correct it in the section it came from and the chart follows.',
        ]],
        'tl' => ['title' => 'Analytics at Insights', 'lines' => [
            'Pareho ring record ang binabasa ng mga chart. Walang inilalagay dito.',
            'Sumusunod ang mga numero sa panahon at filter na napili sa page.',
            'Kung mali ang numero, itama sa section na pinanggalingan at susunod ang chart.',
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
