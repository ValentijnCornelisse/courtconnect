document.addEventListener('click', (gebeurtenis) => {
    const verwijder = gebeurtenis.target.closest('[data-verwijder-tarief]');
    if (verwijder) { verwijder.closest('.tariefregel').remove(); }
});
document.getElementById('tarief-toevoegen')?.addEventListener('click', () => {
    const lijst = document.getElementById('tariefregels');
    const index = Date.now();
    const regel = document.createElement('div');
    regel.className = 'tariefregel';
    const velden = [['dagtype','Dagtype','select'],['starttijd','Van','time'],['eindtijd','Tot','time'],['prijs_per_uur','Prijs per uur (€)','number']];
    for (const [naam, tekst, soort] of velden) {
        const groep = document.createElement('div'); groep.className = 'form-veld';
        const label = document.createElement('label'); label.textContent = tekst;
        const invoer = document.createElement(soort === 'select' ? 'select' : 'input');
        invoer.name = 'tarieven[' + index + '][' + naam + ']'; invoer.id = naam + '-' + index;
        label.htmlFor = invoer.id; invoer.required = true;
        if (soort === 'select') {
            for (const waarde of ['doordeweeks','weekend']) { const optie = document.createElement('option'); optie.value = waarde; optie.textContent = waarde; invoer.append(optie); }
        } else { invoer.type = soort; if (soort === 'number') { invoer.min = '0'; invoer.step = '0.01'; } }
        groep.append(label,invoer); regel.append(groep);
    }
    const knop = document.createElement('button'); knop.type = 'button'; knop.className = 'knop gevaar'; knop.dataset.verwijderTarief = ''; knop.textContent = 'Verwijder regel'; regel.append(knop); lijst.append(regel); regel.querySelector('select').focus();
});
