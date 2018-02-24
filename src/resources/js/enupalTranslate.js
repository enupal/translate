/**
 * Translate plugin for Craft CMS 3.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

(function($)
{
    /**
     * EnupalTranslate class
     */
    var EnupalTranslate = Garnish.Base.extend({

        options: null,
        $toHeader:null,
        $menu: null,
        $form: null,

        /**
         * The constructor.
         */
        init: function()
        {
            this.addListener($('#save-elements-button'), 'activate', 'processAjaxCall');
            this.$form = $("#translate-ajax");
            var settings = {};
            this.$menu = new Garnish.MenuBtn("#enupal-menubtn", settings);
            var $siteMenu = $('.sitemenubtn:first').menubtn().data('menubtn').menu;
            var $siteIdInput = $('input[name="siteId"]');

            // Upload file on click
            $('.translations-upload-button').click(function() {
                $('input[name="translations-upload"]').click().change(function() {
                    $(this).parent('form').submit();
                });
            });

            // Init the form
            if(Craft.getLocalStorage('BaseElementIndex.siteId')) {
                $siteIdInput.val(Craft.getLocalStorage('BaseElementIndex.siteId'));
            }

            // Change the siteId when on hidden values
            $siteMenu.on('optionselect', function(ev) {
                $siteIdInput.val($(ev.selectedOption).data('siteId'));
            });

            Craft.elementIndex.on('afterAction', this.manageAfterAction);
            this.$menu.on('optionSelect', this.manageMenu)
        },

        manageMenu: function(event)
        {
            var data = {
                siteId: Craft.elementIndex.siteId,
                sourceKey: Craft.elementIndex.sourceKey
            };

            Craft.postActionRequest('enupal-translate/translate/download', data, $.proxy(function(response, textStatus) {
                if (textStatus === 'success') {
                    if (response.success)
                    {
                        if (response.filePath){
                            var $iframe = $('<iframe/>', {'src': Craft.getActionUrl('enupal-translate/translate/download-csv-file', {'filepath': response.filePath})}).hide();
                            $("#translate-ajax").append($iframe);
                            Craft.cp.displayNotice(Craft.t('enupal-translate', 'Downloading file'));
                        }
                        else {
                            Craft.cp.displayError(Craft.t('app', 'There was an error when generating the file'));
                        }
                    }
                    else {
                        Craft.cp.displayError(Craft.t('app', 'Please select a different source'));
                    }
                }
                else {
                    Craft.cp.displayError(Craft.t('app', 'An unknown error occurred.'));
                }
            }, this));
        },

        manageAfterAction: function(action, params)
        {
            Craft.elementIndex.updateElements();
        },

        processAjaxCall: function(event)
        {
            event.preventDefault();
            var data = this.$form.serialize();
            Craft.postActionRequest('enupal-translate/translate/save', data, $.proxy(function(response, textStatus) {
                if (textStatus === 'success') {
                    if (response.success)
                    {
                        Craft.cp.displayNotice(Craft.t('enupal-translate', 'Translations saved'));
                        Craft.elementIndex.updateElements();
                    }
                }
                else {
                    Craft.cp.displayError(Craft.t('app', 'An unknown error occurred.'));
                }
            }, this));
        }
    });

    window.EnupalTranslate = EnupalTranslate;

})(jQuery);