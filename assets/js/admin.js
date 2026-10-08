(function (window, document, wp) {
    'use strict';

    // Global DokenOxAdmin utility namespace
    window.DokenOxAdmin = window.DokenOxAdmin || {
        toast: function (message, type) {
            type = type || 'success';
            var existing = document.getElementById('dox-toast-notice');
            if (existing) existing.remove();

            var toast = document.createElement('div');
            toast.id = 'dox-toast-notice';
            toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:10px;box-shadow:0 10px 30px rgba(0,0,0,0.5);transition:all .3s ease;transform:translateY(20px);opacity:0;font-family:Inter,sans-serif;';
            
            if (type === 'success') {
                toast.style.background = '#10B981';
                toast.style.color = '#FFFFFF';
            } else if (type === 'warning') {
                toast.style.background = '#F59E0B';
                toast.style.color = '#111827';
            } else if (type === 'error') {
                toast.style.background = '#EF4444';
                toast.style.color = '#FFFFFF';
            } else {
                toast.style.background = '#6366F1';
                toast.style.color = '#FFFFFF';
            }

            toast.innerHTML = '<span>' + message + '</span>';
            document.body.appendChild(toast);

            setTimeout(function () {
                toast.style.transform = 'translateY(0)';
                toast.style.opacity = '1';
            }, 10);

            setTimeout(function () {
                toast.style.transform = 'translateY(20px)';
                toast.style.opacity = '0';
                setTimeout(function () { toast.remove(); }, 300);
            }, 3000);
        }
    };

    // React Module Mounting (Legacy / Progressive support)
    const mounted = new WeakSet();
    const modules = {};

    const helpers = {
        wp: wp || {},
        render: wp && wp.element ? wp.element.render : null,
        element: wp && wp.element ? wp.element : null,
        i18n: (wp && wp.i18n) || {
            __: (str) => str,
        },
        api: {
            request: (args) => (wp && wp.apiFetch ? wp.apiFetch(args) : Promise.reject('No apiFetch')),
            get: (path) => (wp && wp.apiFetch ? wp.apiFetch({ path }) : Promise.reject('No apiFetch')),
        },
        charts: window.DokenOxCharts || null,
        strings: window.DokenOxPro?.i18n || {},
    };

    function bootstrap() {
        if (!wp || !wp.element) return;
        const nodes = document.querySelectorAll('#doken-ox-admin-app[data-screen]');
        nodes.forEach((node) => {
            if (mounted.has(node)) {
                return;
            }
            const key = node.dataset.screen;
            if (modules[key]) {
                mounted.add(node);
                modules[key](node, helpers);
            }
        });
    }

    window.DokenOxRegisterModule = function registerModule(name, callback) {
        modules[name] = callback;
        bootstrap();
    };

    document.addEventListener('DOMContentLoaded', bootstrap);
})(window, document, window.wp || {});
