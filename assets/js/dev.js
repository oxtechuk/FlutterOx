(function (window) {
    'use strict';

    if (typeof window.DokenOxRegisterModule !== 'function') {
        return;
    }

    window.DokenOxRegisterModule('dev', function (root, helpers) {
        const { element, render } = helpers;
        const { createElement: h, useEffect, useState } = element;
        const { __ } = helpers.i18n;

        const SectionManager = () => {
            const [sections, setSections] = useState([]);
            const [loading, setLoading] = useState(true);
            const [sectionType, setSectionType] = useState('banners');
            const [form, setForm] = useState({ title: '', subtitle: '', link_url: '', section: 'banners', sort_order: 0 });

            const fetchSections = () => {
                setLoading(true);
                helpers.api
                    .get(`/doken-ox/v1/admin/home/sections?section=${sectionType}`)
                    .then((response) => {
                        const items = response && response.data;
                        setSections(Array.isArray(items) ? items : []);
                    })
                    .catch(() => setSections([]))
                    .finally(() => setLoading(false));
            };

            useEffect(() => {
                fetchSections();
            }, [sectionType]);

            const onSubmit = (event) => {
                event.preventDefault();
                helpers.api
                    .request({
                        path: '/doken-ox/v1/admin/home/sections',
                        method: 'POST',
                        data: form,
                    })
                    .then(() => {
                        setForm({ title: '', subtitle: '', link_url: '', section: sectionType, sort_order: 0 });
                        fetchSections();
                    });
            };

            const onDelete = (id) => {
                if (!window.confirm(__('Delete this section?', 'doken-ox-pro'))) {
                    return;
                }
                helpers.api
                    .request({
                        path: `/doken-ox/v1/admin/home/sections/${id}`,
                        method: 'DELETE',
                    })
                    .then(fetchSections);
            };

            const rows = Array.isArray(sections) ? sections : [];

            return h(
                'div',
                null,
                h(
                    'div',
                    { className: 'doken-ox-toolbar', style: { marginBottom: '20px' } },
                    h(
                        'select',
                        {
                            value: sectionType,
                            onChange: (event) => setSectionType(event.target.value),
                        },
                        ['banners', 'featured_stores', 'special_offers'].map((slug) =>
                            h('option', { key: slug, value: slug }, slug)
                        )
                    )
                ),
                h(
                    'form',
                    { className: 'doken-ox-card', onSubmit },
                    h('h2', null, __('إضافة عنصر جديد', 'doken-ox-pro')),
                    h('input', {
                        type: 'text',
                        placeholder: __('العنوان', 'doken-ox-pro'),
                        value: form.title,
                        onChange: (event) => setForm({ ...form, title: event.target.value }),
                        required: true,
                    }),
                    h('input', {
                        type: 'text',
                        placeholder: __('النص الفرعي', 'doken-ox-pro'),
                        value: form.subtitle,
                        onChange: (event) => setForm({ ...form, subtitle: event.target.value }),
                    }),
                    h('input', {
                        type: 'url',
                        placeholder: __('رابط التحويل', 'doken-ox-pro'),
                        value: form.link_url,
                        onChange: (event) => setForm({ ...form, link_url: event.target.value }),
                    }),
                    h('input', {
                        type: 'number',
                        placeholder: __('ترتيب العرض', 'doken-ox-pro'),
                        value: form.sort_order,
                        onChange: (event) => setForm({ ...form, sort_order: parseInt(event.target.value, 10) || 0 }),
                    }),
                    h(
                        'button',
                        { type: 'submit', className: 'button button-primary' },
                        __('حفظ', 'doken-ox-pro')
                    )
                ),
                loading
                    ? h('p', null, helpers.strings.loading || __('Loading…', 'doken-ox-pro'))
                    : h(
                          'table',
                          { className: 'doken-ox-table', style: { marginTop: '30px' } },
                          h(
                              'thead',
                              null,
                              h(
                                  'tr',
                                  null,
                                  h('th', null, __('ID', 'doken-ox-pro')),
                                  h('th', null, __('Title', 'doken-ox-pro')),
                                  h('th', null, __('Link', 'doken-ox-pro')),
                                  h('th', null, __('Actions', 'doken-ox-pro'))
                              )
                          ),
                          h(
                              'tbody',
                              null,
                              rows.map((section) =>
                                  h(
                                      'tr',
                                      { key: section.id },
                                      h('td', null, section.id),
                                      h('td', null, section.title),
                                      h('td', null, section.link_url),
                                      h(
                                          'td',
                                          null,
                                          h(
                                              'button',
                                              {
                                                  type: 'button',
                                                  className: 'button-link-delete',
                                                  onClick: () => onDelete(section.id),
                                              },
                                              __('Delete', 'doken-ox-pro')
                                          )
                                      )
                                  )
                              )
                          )
                      )
            );
        };

        render(h(SectionManager), root);
    });
})(window);

