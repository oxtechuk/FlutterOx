(function (window) {
    'use strict';

    if (typeof window.DokenOxRegisterModule !== 'function') {
        return;
    }

    window.DokenOxRegisterModule('settings', function (root, helpers) {
        const { element, render } = helpers;
        const { createElement: h, useEffect, useState } = element;
        const { __ } = helpers.i18n;

        const Settings = () => {
            const [licenseKey, setLicenseKey] = useState('');
            const [status, setStatus] = useState('idle'); // idle, validating, valid, invalid
            const [message, setMessage] = useState('');

            const handleVerify = () => {
                setStatus('validating');
                helpers.api.apiFetch({
                    path: '/doken-ox/v1/admin/settings/verify-license',
                    method: 'POST',
                    data: { license_key: licenseKey }
                }).then(res => {
                    setStatus('valid');
                    setMessage(res.message);
                }).catch(err => {
                    setStatus('invalid');
                    setMessage(err.message || 'Verification failed');
                });
            };

            return h('div', { className: 'dox-dashboard' }, [
                // Header
                h('div', { className: 'dox-header' }, 
                    h('h2', null, __('Plugin Settings & Information', 'doken-ox-pro'))
                ),

                // Grid
                h('div', { className: 'dox-grid', style: { gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))' } }, [
                    
                    // Plugin Info Card
                    h('div', { className: 'dox-card' }, [
                        h('h3', null, 'Plugin Information'),
                        h('ul', { style: { listStyle: 'none', padding: 0, marginTop: '1rem' } }, [
                            h('li', { style: { marginBottom: '8px' } }, [
                                h('strong', null, 'Name: '), 'Doken Ox Pro'
                            ]),
                            h('li', { style: { marginBottom: '8px' } }, [
                                h('strong', null, 'Version: '), '0.1.0'
                            ]),
                            h('li', { style: { marginBottom: '8px' } }, [
                                h('strong', null, 'Description: '), 'Backend API for mobile apps (WooCommerce + Dokan).'
                            ]),
                        ])
                    ]),

                    // Designer Info Card
                    h('div', { className: 'dox-card' }, [
                        h('h3', null, 'Designer Info'),
                        h('div', { style: { display: 'flex', alignItems: 'center', marginTop: '1rem' } }, [
                            h('div', { 
                                style: { 
                                    width: '60px', height: '60px', borderRadius: '50%', 
                                    backgroundColor: '#e2e8f0', display: 'flex', alignItems: 'center', 
                                    justifyContent: 'center', marginRight: '16px'
                                } 
                            }, h('span', { className: 'dashicons dashicons-admin-users', style: { fontSize: '24px' } })),
                            h('div', null, [
                                h('h4', { style: { margin: 0 } }, 'Eng. Ahmed'),
                                h('p', { style: { margin: '4px 0 0', color: '#64748b' } }, 'Lead Developer & Architect')
                            ])
                        ])
                    ]),

                    // License Card
                    h('div', { className: 'dox-card' }, [
                        h('h3', null, 'License Verification'),
                        h('p', { style: { color: '#64748b', fontSize: '0.9rem' } }, 'Enter one of the 10 fixed temporary serial numbers.'),
                        
                        h('div', { style: { marginTop: '1rem', display: 'flex', gap: '8px' } }, [
                            h('input', { 
                                type: 'text', 
                                placeholder: 'DOX-PRO-XXXX-TEMP',
                                value: licenseKey,
                                onChange: (e) => setLicenseKey(e.target.value),
                                style: { flex: 1, padding: '8px', border: '1px solid #cbd5e1', borderRadius: '4px' }
                            }),
                            h('button', { 
                                className: 'button button-primary',
                                onClick: handleVerify,
                                disabled: status === 'validating'
                            }, status === 'validating' ? 'Verifying...' : 'Verify')
                        ]),

                        message && h('div', { 
                            style: { 
                                marginTop: '1rem', 
                                padding: '8px', 
                                borderRadius: '4px',
                                backgroundColor: status === 'valid' ? '#dcfce7' : '#fee2e2',
                                color: status === 'valid' ? '#166534' : '#991b1b',
                                border: `1px solid ${status === 'valid' ? '#bbf7d0' : '#fecaca'}`
                            } 
                        }, message)
                    ]),
                ])
            ]);
        };

        render(h(Settings), root);
    });
})(window);
