/**
 * Vipps plugin for Craft CMS
 *
 * Vipps JS
 *
 * @author    Superbig
 * @copyright Copyright (c) 2018 Superbig
 * @link      https://superbig.co
 * @package   Vipps
 * @since     1.0.0
 */
(function() {
    var formUrl = '{{ siteUrl('vipps/express/checkout') }}';
    var formId = '{{ expressButtonId ~ 'Form' }}';
    var $button = document.getElementById('{{ expressButtonId }}');
    var clickHandler = (e) => {
        const $targetButton = e.currentTarget;
        const $form = document.createElement('div');
        $form.innerHTML = `
        <form name="{{ expressButtonId ~ 'Form' }}" method="post" action="{{ siteUrl('vipps/express/checkout') }}">
            {{ csrfInput() }}
            {% if purchasable %}
                {% for key,value in params %}
                    {% if key == 'options' %}
                        {% for optionKey, optionValue in value %}
                            <input type="hidden" name="purchasables[1][{{ key }}][{{ optionKey }}]" value="{{ optionValue }}">
                        {% endfor %}
                    {% else %}
                        {% if value %}
                            <input type="hidden" name="purchasables[1][{{ key }}]" value="{{ value }}">
                        {% endif %}
                    {% endif %}
                {% endfor %}
            {% endif %}
        </form>
        `;

        document.body.appendChild($form);
        document[formId].submit();
    }

    $button.addEventListener('click', clickHandler);
})();