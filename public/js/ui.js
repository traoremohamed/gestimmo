/* Plateforme immobilière — comportements communs de l'interface (repris de MARIEATTIEKESAAS) */
(function () {
    'use strict';

    var stockage = {
        lire: function (cle) { try { return localStorage.getItem(cle); } catch (e) { return null; } },
        ecrire: function (cle, valeur) { try { localStorage.setItem(cle, valeur); } catch (e) { /* navigation privée */ } }
    };

    // thème clair / sombre (préférence par navigateur)
    var theme = stockage.lire('ui.theme');
    if (theme) { document.documentElement.setAttribute('data-bs-theme', theme); }

    document.addEventListener('DOMContentLoaded', function () {
        var body = document.body;

        if (stockage.lire('ui.mini') === '1' && window.innerWidth >= 992) { body.classList.add('ui-mini'); }

        document.querySelectorAll('[data-ui="menu"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (window.innerWidth < 992) { body.classList.toggle('ui-menu-ouvert'); return; }
                body.classList.toggle('ui-mini');
                stockage.ecrire('ui.mini', body.classList.contains('ui-mini') ? '1' : '0');
            });
        });
        var voile = document.querySelector('.ui-voile');
        if (voile) { voile.addEventListener('click', function () { body.classList.remove('ui-menu-ouvert'); }); }

        document.querySelectorAll('[data-ui="theme"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var actuel = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-bs-theme', actuel);
                stockage.ecrire('ui.theme', actuel);
            });
        });

        // menu imbriqué : ouverture / fermeture de chaque niveau
        document.querySelectorAll('.ui-menu .ui-groupe').forEach(function (lien) {
            lien.addEventListener('click', function (e) {
                e.preventDefault();
                if (body.classList.contains('ui-mini')) { body.classList.remove('ui-mini'); }
                lien.parentElement.classList.toggle('ouvert');
            });
        });

        // filtre du menu : affiche les nœuds correspondants et leurs ancêtres
        var filtre = document.getElementById('ui-filtre-menu');
        if (filtre) {
            var normaliser = function (s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
            filtre.addEventListener('input', function () {
                var terme = normaliser(filtre.value.trim());
                var noeuds = document.querySelectorAll('.ui-menu .ui-noeud');
                noeuds.forEach(function (n) { n.style.display = ''; n.classList.remove('filtre-ouvert'); });
                document.querySelectorAll('.ui-menu .ui-module').forEach(function (m) { m.style.display = ''; });
                if (!terme) { return; }
                noeuds.forEach(function (n) {
                    var texte = normaliser(n.querySelector(':scope > .ui-lien').textContent);
                    var descendant = Array.prototype.some.call(n.querySelectorAll('.ui-lien'), function (l) { return normaliser(l.textContent).indexOf(terme) !== -1; });
                    n.style.display = descendant || texte.indexOf(terme) !== -1 ? '' : 'none';
                    if (descendant) { n.classList.add('ouvert'); }
                });
                document.querySelectorAll('.ui-menu .ui-module').forEach(function (m) {
                    var visible = Array.prototype.some.call(m.querySelectorAll(':scope > ul > .ui-noeud'), function (n) { return n.style.display !== 'none'; });
                    m.style.display = visible ? '' : 'none';
                });
            });
        }

        // lignes de tableau cliquables (les liens, boutons et formulaires de la ligne gardent leur action)
        document.addEventListener('click', function (e) {
            var ligne = e.target.closest('tr[data-href]');
            if (!ligne || e.target.closest('a, button, input, select, textarea, label, form')) { return; }
            if (e.ctrlKey || e.metaKey) { window.open(ligne.dataset.href, '_blank'); } else { window.location = ligne.dataset.href; }
        });

        // toasts
        document.querySelectorAll('.ui-toast').forEach(function (t) {
            setTimeout(function () { t.style.transition = 'opacity .4s'; t.style.opacity = '0'; setTimeout(function () { t.remove(); }, 400); }, 6000);
            var fermer = t.querySelector('[data-fermer]');
            if (fermer) { fermer.addEventListener('click', function () { t.remove(); }); }
        });

        // confirmation des actions sensibles (annulation, validation...)
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { e.preventDefault(); }
        });

        if (window.jQuery) {
            var $ = window.jQuery;
            if ($.fn.select2) {
                $('.select2').each(function () {
                    var $el = $(this);
                    var options = { theme: 'bootstrap-5', width: '100%', dropdownParent: $el.closest('.modal').length ? $el.closest('.modal') : $(document.body),
                        placeholder: $el.data('placeholder') || '-- Sélectionner --', allowClear: !$el.prop('required') };
                    // longues listes : recherche côté serveur
                    if ($el.data('ajax')) {
                        options.minimumInputLength = 2;
                        options.ajax = { url: $el.data('ajax'), dataType: 'json', delay: 250, data: function (p) { return { q: p.term }; } };
                    }
                    $el.select2(options);
                });
            }
            if ($.fn.DataTable) {
                $('#exampleData, .exampleData, .ui-table').each(function () {
                    if ($.fn.DataTable.isDataTable(this)) { return; }
                    $(this).DataTable({ order: [], pageLength: 25, language: { url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/fr-FR.json' } });
                });
            }
        }
    });

    // utilitaires partagés par les écrans dynamiques (plan de salle, cuisine, analyse…)
    window.Ui = {
        echapper: function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); },
        montant: function (v) { return Number(v || 0).toLocaleString('fr-FR', { maximumFractionDigits: 0 }); },
        api: function (methode, url, donnees) {
            var jeton = document.querySelector('meta[name="csrf-token"]');
            return fetch(url, { method: methode, credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': jeton ? jeton.content : '', 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: donnees && methode !== 'GET' ? JSON.stringify(donnees) : undefined })
                .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) { throw new Error(j.message || ('Erreur ' + r.status)); } return j; }); });
        },
        toast: function (message, type) {
            var zone = document.querySelector('.ui-toasts');
            if (!zone) { window.alert(message); return; }
            var t = document.createElement('div');
            t.className = 'ui-toast ui-toast-' + (type || 'success');
            t.innerHTML = '<i class="ti ti-' + (type === 'danger' ? 'alert-circle' : 'circle-check') + '"></i><div class="flex-grow-1 small"></div>';
            t.querySelector('div').textContent = message;
            zone.appendChild(t);
            setTimeout(function () { t.style.transition = 'opacity .4s'; t.style.opacity = '0'; setTimeout(function () { t.remove(); }, 400); }, 5000);
        }
    };
})();
