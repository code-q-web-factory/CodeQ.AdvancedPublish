document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('neos-module-management-workspaces');
    window.exit = function () {
        window.parent.postMessage('closePublicationPopup', window.parent.origin)
    }
    window.addEventListener('DOMContentLoaded', (event) => {
        jQuery(function($) {
            jQuery('#check-all').change(function() {
                var value = false;
                if (jQuery(this).is(':checked')) {
                    value = true;
                    jQuery('.batch-action').removeClass('neos-hidden').removeClass('neos-disabled').removeAttr('disabled');
                } else {
                    jQuery('.batch-action').addClass('neos-hidden').addClass('neos-disabled').attr('disabled', 'disabled');
                }
                jQuery('tbody input[type="checkbox"]').prop('checked', value);
            });

            jQuery('.neos-check-document').change(function() {
                var documentIdentifier = jQuery(this).val();
                var checked = jQuery(this).prop('checked');
                jQuery(this).closest('table').find('tr.neos-change.document-' + documentIdentifier + ' td.check input').prop('checked', checked);
            });

            jQuery('tbody input[type="checkbox"]').change(function() {
                if (jQuery(this).closest('tr').data('ismoved') === true || jQuery(this).closest('tr').data('isnew') === true) {
                    var currentNodePath = jQuery(this).closest('tr').attr('data-nodepath') + '/';
                    var checked = jQuery(this).prop('checked');

                    function nodePathStartsWith(nodePath) {
                        return function(index, element) {
                            return nodePath.indexOf(jQuery(element).data('nodepath')) === 0;
                        }
                    }
                    var movedOrNewParentDocuments = jQuery(this).closest('table').find('.neos-document[data-ismoved="true"], .neos-document[data-isnew="true"]').filter(nodePathStartsWith(currentNodePath));
                    jQuery(movedOrNewParentDocuments).each(function(index, movedParentDocument) {
                        jQuery('tr[data-nodepath^="' + jQuery(movedParentDocument).data('nodepath') + '"] td.check input').prop('checked', checked);
                    });
                }

                if (jQuery('tbody input[type="checkbox"]:checked').length > 0) {
                    jQuery('.batch-action').removeClass('neos-hidden').removeClass('neos-disabled').removeAttr('disabled')
                } else {
                    jQuery('.batch-action').addClass('neos-hidden').addClass('neos-disabled').attr('disabled', 'disabled');
                }
            });

            jQuery('.fold-toggle').click(function() {
                jQuery(this).toggleClass('fas fa-chevron-down fas fa-chevron-up');
                jQuery('tr.' + jQuery(this).data('toggle')).toggle();
            });
        });
    });

    const reviewerSelectField = document.querySelector('[name*="reviewer"]');
    if (reviewerSelectField) {
        reviewerSelectField.addEventListener('change', (event) => {
            const reviewerField = event.target
            const form = document.querySelector('form')
            const currentUserIdentifier = form.dataset.currentuser
            const currentUserAsReviewerWarning = document.getElementById('currentUserAsReviewerWarning')
            const closestControlGroup = reviewerField.closest('.neos-control-group')
            const submitButton = document.getElementById('submit')
            const submitAndApproveButton = document.getElementById('submit-and-approve')

            if (reviewerField.value === currentUserIdentifier) {
                currentUserAsReviewerWarning.style.display = 'block'
                closestControlGroup?.classList.add('neos-warning')
                submitButton.classList.add('neos-hidden')
                submitAndApproveButton.classList.remove('neos-hidden')
            } else {
                currentUserAsReviewerWarning.style.display = 'none'
                closestControlGroup?.classList.remove('neos-warning')
                submitButton.classList.remove('neos-hidden')
                submitAndApproveButton.classList.add('neos-hidden')
            }
        })
    }
})
