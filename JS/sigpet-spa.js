(function () {
    'use strict';

    if (window.__sigpetSpaInit) {
        return;
    }
    window.__sigpetSpaInit = true;

    const SELECTOR_MAIN = 'main.dashboard-main';

    const SVG_ICONS = {
        success: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
        error: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        close: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
    };

    function obtenerToastStack() {
        let stack = document.getElementById('sigpetToastStack');
        if (!stack) {
            stack = document.createElement('div');
            stack.id = 'sigpetToastStack';
            stack.className = 'sigpet-toast-stack';
            stack.setAttribute('aria-live', 'polite');
            document.body.appendChild(stack);
        }
        return stack;
    }

    function cerrarToast(toast) {
        if (!toast || toast.classList.contains('is-closing')) return;
        toast.classList.add('is-closing');
        setTimeout(function () {
            toast.remove();
        }, 200);
    }

    function lanzarToast(tipo, texto) {
        if (!texto) return;
        const stack = obtenerToastStack();
        const toast = document.createElement('div');
        toast.className = 'sigpet-toast sigpet-toast-' + tipo;
        toast.setAttribute('role', 'status');

        const iconoWrap = document.createElement('span');
        iconoWrap.className = 'sigpet-toast-icon';
        iconoWrap.innerHTML = SVG_ICONS[tipo] || SVG_ICONS.success;

        const cuerpo = document.createElement('div');
        cuerpo.className = 'sigpet-toast-body';
        const titulo = document.createElement('span');
        titulo.className = 'sigpet-toast-title';
        titulo.textContent = tipo === 'error' ? 'No se pudo completar' : 'Operación completada';
        const msg = document.createElement('p');
        msg.className = 'sigpet-toast-msg';
        msg.textContent = texto;
        cuerpo.appendChild(titulo);
        cuerpo.appendChild(msg);

        const btnCerrar = document.createElement('button');
        btnCerrar.type = 'button';
        btnCerrar.className = 'sigpet-toast-close';
        btnCerrar.setAttribute('aria-label', 'Cerrar notificación');
        btnCerrar.innerHTML = SVG_ICONS.close;
        btnCerrar.addEventListener('click', function () {
            cerrarToast(toast);
        });

        toast.appendChild(iconoWrap);
        toast.appendChild(cuerpo);
        toast.appendChild(btnCerrar);
        stack.appendChild(toast);

        let timer = setTimeout(function () { cerrarToast(toast); }, 7000);
        toast.addEventListener('mouseenter', function () { clearTimeout(timer); });
        toast.addEventListener('mouseleave', function () {
            timer = setTimeout(function () { cerrarToast(toast); }, 3500);
        });
    }

    let modalActivo = null;

    function cerrarDetalleModal() {
        if (!modalActivo) return;
        modalActivo.remove();
        modalActivo = null;
        document.body.style.overflow = '';
    }

    function abrirDetalleModal(datos) {
        if (!datos || typeof datos !== 'object') return;
        cerrarDetalleModal();

        const backdrop = document.createElement('div');
        backdrop.className = 'sigpet-detail-backdrop';
        backdrop.setAttribute('role', 'dialog');
        backdrop.setAttribute('aria-modal', 'true');

        const modal = document.createElement('div');
        modal.className = 'sigpet-detail-modal';

        const header = document.createElement('div');
        header.className = 'sigpet-detail-header';

        const heading = document.createElement('div');
        heading.className = 'sigpet-detail-heading';

        if (datos.eyebrow) {
            const eyebrow = document.createElement('div');
            eyebrow.className = 'sigpet-detail-eyebrow';
            eyebrow.textContent = datos.eyebrow;
            heading.appendChild(eyebrow);
        }

        const title = document.createElement('h2');
        title.className = 'sigpet-detail-title';
        title.textContent = datos.title || 'Detalle del registro';
        heading.appendChild(title);

        if (datos.subtitle) {
            const sub = document.createElement('p');
            sub.className = 'sigpet-detail-subtitle';
            sub.textContent = datos.subtitle;
            heading.appendChild(sub);
        }

        const headerActions = document.createElement('div');
        headerActions.className = 'sigpet-detail-header-actions';

        if (datos.badge && datos.badge.label) {
            const pill = document.createElement('span');
            pill.className = 'sigpet-detail-pill tone-' + (datos.badge.tone || 'neutral');
            pill.textContent = datos.badge.label;
            headerActions.appendChild(pill);
        }

        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'sigpet-detail-close';
        closeBtn.setAttribute('aria-label', 'Cerrar ventana de detalle');
        closeBtn.innerHTML = SVG_ICONS.close;
        closeBtn.addEventListener('click', cerrarDetalleModal);
        headerActions.appendChild(closeBtn);

        header.appendChild(heading);
        header.appendChild(headerActions);
        modal.appendChild(header);

        const body = document.createElement('div');
        body.className = 'sigpet-detail-body';

        if (Array.isArray(datos.meta) && datos.meta.length > 0) {
            const grid = document.createElement('div');
            grid.className = 'sigpet-detail-grid';

            datos.meta.forEach(function (item) {
                if (!item || !item.label) return;
                const valText = (item.value === null || item.value === undefined || item.value === '') ? 'No registrado' : String(item.value);
                const cell = document.createElement('div');
                cell.className = 'sigpet-detail-item';

                const lbl = document.createElement('span');
                lbl.className = 'sigpet-detail-item-label';
                lbl.textContent = item.label;

                const val = document.createElement('span');
                val.className = 'sigpet-detail-item-value';
                val.textContent = valText;

                cell.appendChild(lbl);
                cell.appendChild(val);
                grid.appendChild(cell);
            });

            body.appendChild(grid);
        }

        if (Array.isArray(datos.sections) && datos.sections.length > 0) {
            const sectionsWrap = document.createElement('div');
            sectionsWrap.className = 'sigpet-detail-sections';

            datos.sections.forEach(function (sec) {
                if (!sec || !sec.label || !sec.text) return;
                const block = document.createElement('div');
                block.className = 'sigpet-detail-block' + (sec.tone ? ' tone-' + sec.tone : '');

                const lbl = document.createElement('span');
                lbl.className = 'sigpet-detail-block-label';
                lbl.textContent = sec.label;

                const txt = document.createElement('p');
                txt.className = 'sigpet-detail-block-text';
                txt.textContent = sec.text;

                block.appendChild(lbl);
                block.appendChild(txt);
                sectionsWrap.appendChild(block);
            });

            if (sectionsWrap.children.length > 0) {
                body.appendChild(sectionsWrap);
            }
        }

        modal.appendChild(body);

        const footer = document.createElement('div');
        footer.className = 'sigpet-detail-footer';

        if (datos.actionUrl && datos.actionLabel) {
            const actionLink = document.createElement('a');
            actionLink.className = 'sigpet-detail-btn-primary';
            actionLink.href = datos.actionUrl;
            actionLink.textContent = datos.actionLabel;
            footer.appendChild(actionLink);
        }

        const btnCloseFooter = document.createElement('button');
        btnCloseFooter.type = 'button';
        btnCloseFooter.className = 'sigpet-detail-btn-secondary';
        btnCloseFooter.textContent = 'Cerrar';
        btnCloseFooter.addEventListener('click', cerrarDetalleModal);
        footer.appendChild(btnCloseFooter);

        modal.appendChild(footer);
        backdrop.appendChild(modal);

        backdrop.addEventListener('click', function (ev) {
            if (ev.target === backdrop) {
                cerrarDetalleModal();
            }
        });

        document.body.appendChild(backdrop);
        document.body.style.overflow = 'hidden';
        modalActivo = backdrop;
        closeBtn.focus();
    }

    function promoverMensajesAToasts(raiz) {
        if (!raiz) return;
        const mensajes = raiz.querySelectorAll('.success-message, .error-message, .form-message.success, .form-message.error');
        mensajes.forEach(function (el) {
            const texto = (el.textContent || '').trim();
            if (!texto) return;
            const esError = el.classList.contains('error-message') || el.classList.contains('error');
            lanzarToast(esError ? 'error' : 'success', texto);
            el.remove();
        });
    }

    function esEnlaceNavegable(a) {
        if (!a || !a.getAttribute('href')) return false;
        const href = a.getAttribute('href').trim();
        if (
            href === '' ||
            href.startsWith('#') ||
            href.startsWith('javascript:') ||
            href.startsWith('mailto:') ||
            a.hasAttribute('download') ||
            (a.target && a.target !== '_self') ||
            a.dataset.noSpa === '1'
        ) {
            return false;
        }
        try {
            const url = new URL(a.href, window.location.href);
            if (url.origin !== window.location.origin) return false;
            if (url.pathname.endsWith('/logout.php') || url.pathname.endsWith('/exportar.php')) {
                return false;
            }
            return true;
        } catch (e) {
            return false;
        }
    }

    function actualizarSidebarDesdeDoc(doc, finalUrl) {
        const nuevaSidebar = doc.querySelector('#sigpetSidebar');
        const actualSidebar = document.querySelector('#sigpetSidebar');
        if (!nuevaSidebar || !actualSidebar) return;

        const baseUrl = finalUrl || window.location.href;
        const nuevosLinks = nuevaSidebar.querySelectorAll('.sidebar-link');
        const actualesLinks = actualSidebar.querySelectorAll('.sidebar-link');

        actualesLinks.forEach(function (linkActual) {
            const pathActual = new URL(linkActual.href, window.location.href).pathname;
            let match = null;
            nuevosLinks.forEach(function (nuevoLink) {
                const pathNuevo = new URL(nuevoLink.getAttribute('href'), baseUrl).pathname;
                if (pathNuevo === pathActual) {
                    match = nuevoLink;
                }
            });
            if (match) {
                linkActual.setAttribute('href', match.getAttribute('href'));
                linkActual.classList.toggle('active', match.classList.contains('active'));
                const badgeActual = linkActual.querySelector('.sidebar-badge');
                const badgeNuevo = match.querySelector('.sidebar-badge');
                if (badgeNuevo) {
                    if (badgeActual) {
                        badgeActual.textContent = badgeNuevo.textContent;
                    } else {
                        linkActual.appendChild(badgeNuevo.cloneNode(true));
                    }
                } else if (badgeActual) {
                    badgeActual.remove();
                }
            }
        });

        const logoutNuevo = nuevaSidebar.querySelector('.sidebar-logout');
        const logoutActual = actualSidebar.querySelector('.sidebar-logout');
        if (logoutNuevo && logoutActual) {
            logoutActual.setAttribute('href', logoutNuevo.getAttribute('href'));
        }
    }

    function asegurarEstilosPagina(doc, finalUrl) {
        const nuevosLinks = doc.querySelectorAll('head link[rel="stylesheet"]');
        const existentes = new Set(
            Array.from(document.querySelectorAll('head link[rel="stylesheet"]')).map(function (el) {
                return new URL(el.href, window.location.href).pathname;
            })
        );
        nuevosLinks.forEach(function (el) {
            const url = new URL(el.getAttribute('href'), finalUrl || window.location.href);
            if (!existentes.has(url.pathname)) {
                const copia = document.createElement('link');
                copia.rel = 'stylesheet';
                copia.href = url.toString();
                document.head.appendChild(copia);
            }
        });
    }

    function ejecutarScriptsInternos(contenedor) {
        const scripts = contenedor.querySelectorAll('script');
        scripts.forEach(function (oldScript) {
            const s = document.createElement('script');
            if (oldScript.src) {
                s.src = oldScript.src;
            } else {
                s.textContent = oldScript.textContent;
            }
            oldScript.parentNode.replaceChild(s, oldScript);
        });
    }

    function aplicarHtmlParcial(html, finalUrl, pushState) {
        cerrarDetalleModal();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const nuevoMain = doc.querySelector(SELECTOR_MAIN);
        const actualMain = document.querySelector(SELECTOR_MAIN);

        if (!nuevoMain || !actualMain) {
            window.location.href = finalUrl;
            return;
        }

        if (doc.title) {
            document.title = doc.title;
        }

        asegurarEstilosPagina(doc, finalUrl);
        actualizarSidebarDesdeDoc(doc, finalUrl);

        if (pushState && finalUrl) {
            window.history.pushState({ sigpetUrl: finalUrl }, '', finalUrl);
        }

        actualMain.replaceWith(nuevoMain);
        ejecutarScriptsInternos(nuevoMain);
        promoverMensajesAToasts(nuevoMain);

        // Si existen scripts justo después de </main> dentro de .dashboard-layout
        const layoutNuevo = doc.querySelector('.dashboard-layout');
        if (layoutNuevo) {
            const extraScripts = Array.from(layoutNuevo.children).filter(function (el) {
                return el.tagName === 'SCRIPT';
            });
            extraScripts.forEach(function (sc) {
                const s = document.createElement('script');
                s.textContent = sc.textContent;
                nuevoMain.appendChild(s);
            });
        }

        window.scrollTo({ top: 0, behavior: 'instant' });
    }

    async function navegarParcial(url, pushState) {
        const main = document.querySelector(SELECTOR_MAIN);
        if (main) main.classList.add('is-updating');

        try {
            const res = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'SIGPET-Partial' }
            });
            if (res.redirected && res.url.includes('login.php')) {
                window.location.href = res.url;
                return;
            }
            const html = await res.text();
            aplicarHtmlParcial(html, res.url || url, pushState);
        } catch (err) {
            window.location.href = url;
        } finally {
            const currentMain = document.querySelector(SELECTOR_MAIN);
            if (currentMain) currentMain.classList.remove('is-updating');
        }
    }

    function leerPayloadDetalle(el) {
        if (!el || !el.dataset.detail) return null;
        try {
            return JSON.parse(el.dataset.detail);
        } catch (err) {
            return null;
        }
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modalActivo) {
            cerrarDetalleModal();
        }
    });

    document.addEventListener('click', function (e) {
        const closeBtn = e.target.closest('.sigpet-flash-close');
        if (closeBtn) {
            const box = closeBtn.closest('.form-message, .success-message, .error-message, .warning-message, .info-message');
            if (box) box.remove();
            return;
        }

        const detailTrigger = e.target.closest('.sigpet-detail-trigger');
        if (detailTrigger) {
            e.preventDefault();
            const host = detailTrigger.dataset.detail ? detailTrigger : detailTrigger.closest('[data-detail]');
            const payload = leerPayloadDetalle(host);
            if (payload) {
                abrirDetalleModal(payload);
            }
            return;
        }

        if (e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) {
            return;
        }

        const enlace = e.target.closest('a[href]');
        if (enlace) {
            if (!esEnlaceNavegable(enlace)) {
                return;
            }
            e.preventDefault();
            navegarParcial(enlace.href, true);
            return;
        }

        const clickableRow = e.target.closest('tr.sigpet-row-clickable[data-detail]');
        if (clickableRow && !e.target.closest('a, button, form, input, select, textarea, label')) {
            const payload = leerPayloadDetalle(clickableRow);
            if (payload) {
                abrirDetalleModal(payload);
            }
        }
    });

    document.addEventListener('submit', async function (e) {
        if (e.defaultPrevented) return;
        const form = e.target;
        if (!form || !form.closest(SELECTOR_MAIN) || form.dataset.noSpa === '1') {
            return;
        }

        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        const actionAttr = form.getAttribute('action');
        const actionUrl = new URL(actionAttr && actionAttr.trim() !== '' ? actionAttr : window.location.pathname, window.location.href);
        if (actionUrl.origin !== window.location.origin) {
            return;
        }

        e.preventDefault();

        const main = document.querySelector(SELECTOR_MAIN);
        if (main) main.classList.add('is-updating');

        try {
            if (method === 'GET') {
                const params = new URLSearchParams(new FormData(form));
                actionUrl.search = params.toString();
                await navegarParcial(actionUrl.toString(), true);
                return;
            }

            const res = await fetch(actionUrl.toString(), {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'SIGPET-Partial' }
            });
            if (res.redirected && res.url.includes('login.php')) {
                window.location.href = res.url;
                return;
            }
            const html = await res.text();
            aplicarHtmlParcial(html, res.url || actionUrl.toString(), true);
        } catch (err) {
            form.submit();
        } finally {
            const currentMain = document.querySelector(SELECTOR_MAIN);
            if (currentMain) currentMain.classList.remove('is-updating');
        }
    });

    window.addEventListener('popstate', function () {
        navegarParcial(window.location.href, false);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            promoverMensajesAToasts(document.querySelector(SELECTOR_MAIN));
        });
    } else {
        promoverMensajesAToasts(document.querySelector(SELECTOR_MAIN));
    }
})();
