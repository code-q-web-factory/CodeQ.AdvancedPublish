document.addEventListener('DOMContentLoaded', () => {
    // This is necessary for Neos CSS to work in our module
    document.body.classList.add('neos-module-management-workspaces');
    window.exit = function () {
        window.parent.postMessage('closePublicationPopup', window.parent.origin)
    }

    jQuery(function($) {
        jQuery('.fold-toggle').click(function() {
            jQuery(this).toggleClass('fas fa-chevron-down fas fa-chevron-up');
            jQuery('tr.' + jQuery(this).data('toggle')).toggle();
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
