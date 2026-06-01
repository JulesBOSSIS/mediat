/**
 * Tutoriel guidé MediaT — assombrissement + parcours dossier → sous-dossier → document.
 */
(function () {
    'use strict';

    var PAD = 10;
    var STORAGE_KEY = 'mediatTourResumeV1';

    var state = {
        active: false,
        steps: [],
        index: 0,
        root: null,
        shades: [],
        popover: null,
        highlightedEl: null,
        resumeHandler: null,
        onScrollResize: null,
        previousBodyOverflow: '',
    };

    function getVisibleSearchTarget() {
        if (window.matchMedia('(min-width: 992px)').matches) {
            return document.querySelector('[data-tour="tour-search-desktop"]');
        }
        return document.querySelector('[data-tour="tour-search-mobile"]');
    }

    function getVisibleUserMenuTarget() {
        if (window.matchMedia('(min-width: 992px)').matches) {
            return document.querySelector('[data-tour="tour-user-desktop"]');
        }
        return document.querySelector('[data-tour="tour-user-mobile"]');
    }

    function tourEl(id) {
        return document.querySelector('[data-tour="' + id + '"]');
    }

    function detachResumeListener() {
        if (state.resumeHandler) {
            document.removeEventListener('click', state.resumeHandler, true);
            state.resumeHandler = null;
        }
    }

    function attachResumeListener(stepIndex, resumeMode) {
        detachResumeListener();
        if (!resumeMode || !state.active) {
            return;
        }
        state.resumeHandler = function (e) {
            if (!state.active || state.index !== stepIndex) {
                return;
            }
            var ok = false;
            if (resumeMode === 'afterRootFolder') {
                ok = !!e.target.closest('#sidebar a.sidebar-item.folder');
            } else if (resumeMode === 'afterSubFolder') {
                ok = !!e.target.closest('main a.folder-card');
            } else if (resumeMode === 'afterDocumentOpen') {
                var col = e.target.closest('[data-tour="tour-folder-first-document"]');
                ok = !!(col && e.target.closest('a[href*="/document"]'));
            }
            if (!ok) {
                return;
            }
            try {
                sessionStorage.setItem(STORAGE_KEY, resumeMode);
            } catch (err) {}
            stop();
        };
        document.addEventListener('click', state.resumeHandler, true);
    }

    /** Étape finale commune (hors vue document déjà couverte par resume) */
    function pushClosingStep(steps) {
        steps.push({
            centered: true,
            title: 'Fin du tutoriel',
            text:
                'Vous pouvez relancer ce guide à tout moment via le menu utilisateur (« Tutoriel »). Bonne navigation sur MediaT.',
        });
    }

    function buildStepList(resume) {
        var steps = [];

        if (!resume) {
            steps.push({
                centered: true,
                title: 'Bienvenue sur MediaT',
                text:
                    'Ce tutoriel vous fait parcourir la barre latérale puis un dossier, un sous-dossier et une fiche document. Utilisez « Suivant » pour les étapes libres ; quand une action est demandée, suivez la zone mise en évidence puis cliquez — la visite reprendra sur la page suivante.',
            });

            var logo = tourEl('tour-logo');
            if (logo) {
                steps.push({
                    el: logo,
                    title: 'Logo et accès accueil',
                    text:
                        'Cliquez sur le nom MediaT pour revenir à tout moment à la page d’accueil.',
                    placement: 'bottom',
                });
            }

            var searchEl = getVisibleSearchTarget();
            if (searchEl) {
                steps.push({
                    el: searchEl,
                    title: 'Recherche dans la documentation',
                    text:
                        'Saisissez des mots-clés puis lancez la recherche pour trouver des documents dans tous les dossiers.',
                    placement: 'bottom',
                });
            }

            var userEl = getVisibleUserMenuTarget();
            if (userEl) {
                steps.push({
                    el: userEl,
                    title: 'Menu utilisateur',
                    text:
                        'Profil, favoris, déconnexion, et pour les administrateurs le panneau d’administration.',
                    placement: 'left',
                });
            }

            var sidebar = tourEl('tour-sidebar');
            if (sidebar) {
                steps.push({
                    el: sidebar,
                    title: 'Ressources par dossiers',
                    text:
                        'L’arborescence liste les dossiers accessibles. Vous pouvez redimensionner la colonne sur bureau.',
                    placement: 'right',
                });
            }

            var firstFolder = document.querySelector('#sidebar a.sidebar-item.folder');
            if (firstFolder) {
                steps.push({
                    el: firstFolder,
                    title: 'Ouvrir un dossier',
                    text:
                        'Cliquez sur ce premier dossier en haut de la liste pour entrer dans l’arborescence. Le tutoriel se poursuivra automatiquement sur la page du dossier.',
                    placement: 'right',
                    resumeOnClick: 'afterRootFolder',
                });
            } else {
                steps.push({
                    centered: true,
                    title: 'Aucun dossier listé',
                    text:
                        'Aucun dossier n’est visible dans la barre latérale pour cette démo. Utilisez la recherche ou contactez un administrateur.',
                });
                pushClosingStep(steps);
            }

            return steps;
        }

        if (resume === 'afterRootFolder') {
            var sub = tourEl('tour-folder-target-subfolder');
            if (sub) {
                var plusieurs = document.querySelectorAll('main a.folder-card').length >= 2;
                steps.push({
                    el: sub,
                    title: plusieurs ? 'Deuxième sous-dossier' : 'Sous-dossier',
                    text: plusieurs
                        ? 'Descendez d’un niveau : cliquez sur ce deuxième sous-dossier pour affiner la navigation. Le tutoriel reprendra sur la page suivante.'
                        : 'Ouvrez ce sous-dossier pour poursuivre. Le tutoriel reprendra sur la page suivante.',
                    placement: 'bottom',
                    resumeOnClick: 'afterSubFolder',
                });
                return steps;
            }

            var docCol = tourEl('tour-folder-first-document');
            if (docCol) {
                steps.push({
                    el: docCol,
                    title: 'Documents du dossier',
                    text:
                        'Ici figurent les fichiers du dossier. Vous pouvez cocher « Télécharger ce PDF » sur un fichier PDF afin de le télécharger, ou ouvrir la fiche avec le titre ou la zone centrale pour lire en ligne, commenter et ajouter aux favoris — cliquez pour ouvrir la fiche et poursuivre le tutoriel.',
                    placement: 'top',
                    resumeOnClick: 'afterDocumentOpen',
                });
                return steps;
            }

            steps.push({
                centered: true,
                title: 'Contenu du dossier',
                text:
                    'Ce dossier ne propose ni sous-dossier ni fichier dans les listes ci-dessous pour le moment. Revenez à l’accueil ou ouvrez un autre dossier.',
            });
            pushClosingStep(steps);
            return steps;
        }

        if (resume === 'afterSubFolder') {
            var docAfterSub = tourEl('tour-folder-first-document');
            if (docAfterSub) {
                steps.push({
                    el: docAfterSub,
                    title: 'Ouvrir ou télécharger un document',
                    text:
                        'Pour un PDF, la zone « Télécharger ce PDF » télécharge le fichier seul. Pour consulter le document dans l’application (aperçu, favoris, commentaires, note), cliquez sur la carte vers la fiche document.',
                    placement: 'top',
                    resumeOnClick: 'afterDocumentOpen',
                });
                return steps;
            }

            steps.push({
                centered: true,
                title: 'Aucun fichier listé',
                text:
                    'Ce sous-dossier ne contient pas encore de fichiers dans la section « Fichiers ». Essayez un autre dossier ou utilisez la recherche.',
            });
            pushClosingStep(steps);
            return steps;
        }

        if (resume === 'afterDocumentOpen') {
            var docActions = tourEl('tour-document-actions');
            if (docActions) {
                steps.push({
                    el: docActions,
                    title: 'Actions sur le document',
                    text:
                        'Ajoutez ou retirez le document des favoris, téléchargez le PDF (ou le fichier), et revenez à l’accueil si besoin.',
                    placement: 'bottom',
                });
            }

            var docRating = tourEl('tour-document-rating');
            if (docRating) {
                steps.push({
                    el: docRating,
                    title: 'Note',
                    text: 'Attribuez une note pour aider à suivre la qualité du contenu.',
                    placement: 'left',
                });
            }

            var docComments = tourEl('tour-document-comments');
            if (docComments) {
                steps.push({
                    el: docComments,
                    title: 'Discussion',
                    text: 'Lisez les commentaires existants et publiez le vôtre sur ce document.',
                    placement: 'left',
                });
            }

            pushClosingStep(steps);
            return steps;
        }

        return steps;
    }

    function ensureDom() {
        if (state.root) {
            return;
        }
        var root = document.createElement('div');
        root.className = 'mediat-tour-root is-blocking';
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.setAttribute('aria-label', 'Tutoriel');

        var shades = [];
        for (var i = 0; i < 4; i++) {
            var s = document.createElement('div');
            s.className = 'mediat-tour-shade';
            shades.push(s);
            root.appendChild(s);
        }

        var pop = document.createElement('div');
        pop.className = 'mediat-tour-popover';
        root.appendChild(pop);

        document.body.appendChild(root);
        state.root = root;
        state.shades = shades;
        state.popover = pop;
    }

    function teardownHighlight() {
        if (state.highlightedEl) {
            state.highlightedEl.classList.remove('mediat-tour-target-highlight');
            state.highlightedEl = null;
        }
    }

    function repositionShadesFullScreen() {
        var shades = state.shades;
        shades[0].style.cssText =
            'display:block;top:0;left:0;width:100vw;height:100vh;z-index:10050;background:rgba(15,23,42,0.78)';
        shades[1].style.display = 'none';
        shades[2].style.display = 'none';
        shades[3].style.display = 'none';
    }

    function repositionShadesHole(rect) {
        var shades = state.shades;
        shades[1].style.display = 'block';
        shades[2].style.display = 'block';
        shades[3].style.display = 'block';

        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var t = rect.top;
        var l = rect.left;
        var w = rect.width;
        var h = rect.height;

        shades[0].style.cssText =
            'display:block;top:0;left:0;width:100vw;height:' + Math.max(0, t) + 'px;z-index:10050;background:rgba(15,23,42,0.78)';
        shades[1].style.cssText =
            'display:block;top:' +
            (t + h) +
            'px;left:0;width:100vw;height:' +
            Math.max(0, vh - t - h) +
            'px;z-index:10050;background:rgba(15,23,42,0.78)';
        shades[2].style.cssText =
            'display:block;top:' +
            t +
            'px;left:0;width:' +
            Math.max(0, l) +
            'px;height:' +
            h +
            'px;z-index:10050;background:rgba(15,23,42,0.78)';
        shades[3].style.cssText =
            'display:block;top:' +
            t +
            'px;left:' +
            (l + w) +
            'px;width:' +
            Math.max(0, vw - l - w) +
            'px;height:' +
            h +
            'px;z-index:10050;background:rgba(15,23,42,0.78)';
    }

    function placePopoverNear(targetRect, placement) {
        var pop = state.popover;
        pop.style.transform = '';
        pop.classList.remove('mediat-tour-popover--center');

        var tr = {
            left: targetRect.left,
            top: targetRect.top,
            width: targetRect.width,
            height: targetRect.height,
            right: targetRect.left + targetRect.width,
            bottom: targetRect.top + targetRect.height,
        };

        var margin = 12;
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        pop.style.visibility = 'hidden';
        pop.style.left = '0';
        pop.style.top = '0';
        var pr = pop.getBoundingClientRect();
        pop.style.visibility = '';

        var left;
        var top;

        if (placement === 'left') {
            left = tr.left - pr.width - margin;
            top = tr.top + tr.height / 2 - pr.height / 2;
        } else if (placement === 'right') {
            left = tr.right + margin;
            top = tr.top + tr.height / 2 - pr.height / 2;
        } else if (placement === 'top') {
            left = tr.left + tr.width / 2 - pr.width / 2;
            top = tr.top - pr.height - margin;
        } else {
            left = tr.left + tr.width / 2 - pr.width / 2;
            top = tr.bottom + margin;
        }

        left = Math.max(margin, Math.min(left, vw - pr.width - margin));
        top = Math.max(margin, Math.min(top, vh - pr.height - margin));

        pop.style.left = left + 'px';
        pop.style.top = top + 'px';
    }

    function placePopoverCenter() {
        state.popover.classList.add('mediat-tour-popover--center');
        state.popover.style.left = '';
        state.popover.style.top = '';
    }

    function renderPopoverContent(stepIndex, step) {
        var pop = state.popover;
        var total = state.steps.length;
        var html = '';

        html +=
            '<div class="mediat-tour-popover-title"><i class="bi bi-mortarboard text-primary"></i>' +
            escapeHtml(step.title) +
            '</div>';
        html += '<div class="mediat-tour-popover-body">' + escapeHtml(step.text) + '</div>';
        html += '<div class="mediat-tour-actions">';
        html += '<span class="mediat-tour-steps">' + (stepIndex + 1) + ' / ' + total + '</span>';
        html += '<div class="mediat-tour-buttons">';

        if (stepIndex > 0) {
            html +=
                '<button type="button" class="mediat-tour-btn mediat-tour-btn--ghost" data-mediat-action="prev">Précédent</button>';
        }
        html +=
            '<button type="button" class="mediat-tour-btn mediat-tour-btn--ghost" data-mediat-action="close">Terminer</button>';
        var hasNext = stepIndex < total - 1;
        if (hasNext) {
            html +=
                '<button type="button" class="mediat-tour-btn mediat-tour-btn--primary" data-mediat-action="next">Suivant</button>';
        } else {
            html +=
                '<button type="button" class="mediat-tour-btn mediat-tour-btn--primary" data-mediat-action="close">Fermer</button>';
        }
        html += '</div></div>';

        pop.innerHTML = html;

        pop.querySelectorAll('[data-mediat-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var act = btn.getAttribute('data-mediat-action');
                if (act === 'next') {
                    go(1);
                } else if (act === 'prev') {
                    go(-1);
                } else {
                    stop();
                }
            });
        });
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function installHighlight(el) {
        teardownHighlight();
        state.highlightedEl = el;
        el.classList.add('mediat-tour-target-highlight');

        var r2 = el.getBoundingClientRect();
        var padded = {
            top: r2.top - PAD,
            left: r2.left - PAD,
            width: r2.width + PAD * 2,
            height: r2.height + PAD * 2,
        };
        repositionShadesHole(padded);
        return { rect: r2, padded: padded };
    }

    function showStep(i) {
        detachResumeListener();

        state.index = i;
        var step = state.steps[i];
        if (!step) {
            stop();
            return;
        }

        renderPopoverContent(i, step);

        if (step.resumeOnClick) {
            renderPopoverNavForResumeStep(step, i);
        }

        if (step.centered) {
            teardownHighlight();
            repositionShadesFullScreen();
            placePopoverCenter();
            return;
        }

        var el = step.el;
        if (!el || !document.body.contains(el)) {
            go(1);
            return;
        }

        try {
            el.scrollIntoView({ block: 'center', inline: 'nearest' });
        } catch (e) {
            el.scrollIntoView(true);
        }

        requestAnimationFrame(function () {
            if (state.steps[state.index] !== step) {
                return;
            }
            var dims = installHighlight(el);
            placePopoverNear(dims.padded, step.placement || 'bottom');
            if (step.resumeOnClick) {
                attachResumeListener(i, step.resumeOnClick);
            }
        });
    }

    /** Sur les étapes « cliquez pour continuer », masquer Suivant : la suite est le clic. */
    function renderPopoverNavForResumeStep(step, stepIndex) {
        if (step.centered) {
            return;
        }
        var pop = state.popover;
        var nextBtn = pop.querySelector('[data-mediat-action="next"]');
        if (nextBtn) {
            nextBtn.style.display = 'none';
        }
    }

    function go(delta) {
        var next = state.index + delta;
        if (next < 0) {
            next = 0;
        }
        if (next >= state.steps.length) {
            stop();
            return;
        }
        showStep(next);
    }

    function onScrollResize() {
        if (!state.active) {
            return;
        }
        var step = state.steps[state.index];
        if (!step || step.centered) {
            return;
        }
        var el = state.highlightedEl;
        if (!el) {
            return;
        }
        var r2 = el.getBoundingClientRect();
        var padded = {
            top: r2.top - PAD,
            left: r2.left - PAD,
            width: r2.width + PAD * 2,
            height: r2.height + PAD * 2,
        };
        repositionShadesHole(padded);
        placePopoverNear(padded, step.placement || 'bottom');
    }

    function stop() {
        if (!state.active) {
            detachResumeListener();
            return;
        }
        state.active = false;
        detachResumeListener();
        teardownHighlight();
        if (state.root && state.root.parentNode) {
            state.root.parentNode.removeChild(state.root);
        }
        state.root = null;
        state.shades = [];
        state.popover = null;
        document.body.classList.remove('mediat-tour-active');
        document.body.style.overflow = state.previousBodyOverflow;
        document.removeEventListener('keydown', onKey);
        window.removeEventListener('resize', state.onScrollResize);
        window.removeEventListener('scroll', state.onScrollResize, true);
        state.onScrollResize = null;
    }

    function onKey(ev) {
        if (!state.active) {
            return;
        }
        if (ev.key === 'Escape') {
            ev.preventDefault();
            try {
                sessionStorage.removeItem(STORAGE_KEY);
            } catch (e2) {}
            stop();
        }
    }

    function peekPendingResume() {
        try {
            return sessionStorage.getItem(STORAGE_KEY);
        } catch (e) {
            return null;
        }
    }

    function clearPendingResume() {
        try {
            sessionStorage.removeItem(STORAGE_KEY);
        } catch (e) {}
    }

    function start(opts) {
        opts = opts || {};
        if (state.active) {
            stop();
        }
        // Ne pas effacer la clé de reprise si on démarre depuis une page rechargée après un clic guidé
        if (!opts.resume) {
            clearPendingResume();
        }
        state.steps = buildStepList(opts.resume || null);
        if (state.steps.length === 0) {
            return;
        }
        ensureDom();
        state.active = true;
        state.previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        document.body.classList.add('mediat-tour-active');
        document.addEventListener('keydown', onKey);
        state.onScrollResize = function () {
            onScrollResize();
        };
        window.addEventListener('resize', state.onScrollResize);
        window.addEventListener('scroll', state.onScrollResize, true);
        showStep(0);
    }

    /** Reprise automatique après navigation (clic dossier / sous-dossier / lien document). */
    function tryResumeFromStorage() {
        var pending = peekPendingResume();
        if (
            !pending ||
            (pending !== 'afterRootFolder' &&
                pending !== 'afterSubFolder' &&
                pending !== 'afterDocumentOpen')
        ) {
            return;
        }
        clearPendingResume();
        setTimeout(function () {
            start({ resume: pending });
        }, 380);
    }

    /** Reprise : si le script est chargé après DOMContentLoaded (rare), déclencher quand même. */
    function bootTryResume() {
        tryResumeFromStorage();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootTryResume);
    } else {
        bootTryResume();
    }

    window.addEventListener('pageshow', function (ev) {
        if (ev.persisted) {
            bootTryResume();
        }
    });

    window.MediatTour = { start: start, stop: stop, tryResumeFromStorage: tryResumeFromStorage };
})();
