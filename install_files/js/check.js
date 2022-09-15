/*!
 * System Check Page (Step 1)
 */
Installer.Pages.systemCheck.title = 'webinstaller.system_check';
Installer.Pages.systemCheck.nextButton = 'webinstaller.agree_continue';

Installer.Pages.systemCheck.requirements = [
    { code: 'phpVersion', label: 'PHP version 8.0.0 or greater required' },
    { code: 'phpExtensions', label: 'Required PHP Extensions', subreason: 'The [%s] PHP extensions are required.' },
    { code: 'liveConnection', label: 'Test connection to the installation server', reason: 'Check that your server can make outgoing connections to gateway.octobercms.com.' },
    { code: 'writePermission', label: 'Permission to write to directories and files', reason: 'The installer was unable to write to the installation directories and files.' },
];

Installer.Pages.systemCheck.init = function() {
    var checkList = $('#systemCheckList'),
        appEula = $('#appEula').hide(),
        systemCheckFailed = $('#systemCheckFailed').hide(),
        nextButton = $('#nextButton').addClass('disabled'),
        eventChain = [],
        failCodes = [],
        failReasons = [],
        success = true;

    /*
     * Loops each requirement, posts it back and processes the result
     * as part of a waterfall
     */
    $.each(this.requirements, function(index, requirement){
        eventChain.push(function(){
            var deferred = $.Deferred();

            var item = $('<li />').addClass('animated-content move_right').text(requirement.label)
            item.addClass('load animate fade_in')
            checkList.append(item)

            $.sendRequest('onCheckRequirement', { code: requirement.code }, { loadingIndicator: false })
                .done(function(data) {
                    setTimeout(function() {
                        if (data.result) {
                            item.removeClass('load').addClass('pass');
                            deferred.resolve();
                        }
                        else {
                            // Fail the item but continue the waterfall.
                            success = false;
                            failCodes.push(requirement.code);
                            if (data.subChecks && requirement.subreason) {
                                failReasons.push(requirement.subreason.replace('%s', data.subChecks.join(', ')));
                            }
                            if (requirement.reason) {
                                failReasons.push(requirement.reason);
                            }
                            item.removeClass('load').addClass('fail');
                            deferred.resolve();
                        }
                    }, 500)
                }).fail(function(data) {
                    setTimeout(function() {
                        success = false;
                        failCodes.push(requirement.code);
                        if (requirement.reason) {
                            failReasons.push(requirement.reason);
                        }
                        if (data.responseText) {
                            console.log('Failure reason: ' + data.responseText);
                        }
                        item.removeClass('load').addClass('fail');
                        deferred.resolve();
                    }, 500);
                })

            return deferred;
        })
    })

    /*
     * Handle the waterfall result
     */
    $.waterfall.apply(this, eventChain).done(function(){
        if (!success) {
            // Specific reasons are not currently being used.
            systemCheckFailed.show().addClass('animate fade_in');
            systemCheckFailed.renderPartial('check/fail', { code: failCodes.join(', '), reason: failReasons.join(', ') });
        } else {
            // Success
            appEula.show().addClass('animate fade_in');
            nextButton.removeClass('disabled');
        }
    })
}

Installer.Pages.systemCheck.next = function() {
    Installer.showPage('configForm');
}

Installer.Pages.systemCheck.retry = function() {
    var self = Installer.Pages.systemCheck;
    $('#containerBody').html('').renderPartial('check', self);
    self.init();
}
