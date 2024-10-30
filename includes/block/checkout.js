const blockData = window.wc.wcSettings.getSetting('ovri_data', {});

const Content = () => {
    return window.wp.htmlEntities.decodeEntities(blockData.description || '');
};

const plugin_directory_url = '../wp-content/plugins/moneytigo/';  // Remplace par la bonne URL


const label = window.wp.htmlEntities.decodeEntities(blockData.title) || window.wp.i18n.__('Ovri', 'wc-ovri');


const getLabelWithLogos = () => {
    return wp.element.createElement('span', { className: 'method-title' },
        window.wp.htmlEntities.decodeEntities(blockData.title) || window.wp.i18n.__('Ovri', 'wc-ovri'),
        wp.element.createElement('div', { className: 'payment-logos' },
            wp.element.createElement('img', {
                src: plugin_directory_url + 'assets/img/carte.png',
                alt: 'Card OVRI Payment',
                class: 'imagelogoBlockOvri'
            })
        )
    );
};

const Block_Gateway = {
    name: 'ovri',
    label: getLabelWithLogos(),
    content: wp.element.createElement(Content, { label }),  // Passer le label ici
    edit: wp.element.createElement(Content, { label }),   // Passer le label ici aussi
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: blockData.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);
