(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Handle task checkbox changes
        $(document).on('change', '.child-task-checkbox', function() {
            const $checkbox = $(this);
            const $taskItem = $checkbox.closest('.child-task-item');
            const assignmentId = $checkbox.data('assignment-id');
            const isChecked = $checkbox.is(':checked');
            const status = isChecked ? 'completed' : 'todo';
            
            // Disable checkbox during update
            $checkbox.prop('disabled', true);
            $taskItem.addClass('updating');
            
            $.ajax({
                url: rodinneUlohyFrontend.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rodinne_ulohy_update_task_status',
                    nonce: rodinneUlohyFrontend.nonce,
                    assignment_id: assignmentId,
                    status: status
                },
                success: function(response) {
                    if (response.success) {
                        const $overviewRoot = $checkbox.closest('.rodinne-ulohy-child-overview');
                        
                        // Update points balance in header if provided
                        if (response.data.points_balance !== undefined && $overviewRoot.length) {
                            const $balanceElement = $overviewRoot.find('.points-number');
                            if ($balanceElement.length) {
                                $balanceElement.text(response.data.points_balance);
                            }
                        }
                        
                        // Update badge and task item styling instantly based on task status
                        const $badge = $taskItem.find('.task-points-badge');
                        
                        if ($badge.length) {
                            // Get rating from data attribute
                            const rating = parseInt($badge.data('rating') || 0, 10);
                            
                            // Determine if task is completed
                            const isCompleted = $checkbox.is(':checked');
                            
                            // Update task item class
                            if (isCompleted) {
                                $taskItem.addClass('task-completed');
                            } else {
                                $taskItem.removeClass('task-completed');
                            }
                            
                            // Update badge colors and text
                            if (isCompleted) {
                                // Completed: green badge with points and star
                                $badge.css({
                                    'background': '#dcfce7',
                                    'color': '#16a34a'
                                });
                                $badge.text(rating + ' ⭐');
                            } else {
                                // Not completed: red badge with -points and star
                                $badge.css({
                                    'background': '#fee2e2',
                                    'color': '#dc2626'
                                });
                                $badge.text('-' + rating + ' ⭐');
                            }
                        }
                        
                        // Update section progress instantly
                        const $section = $checkbox.closest('.child-tasks-section');
                        if ($section.length) {
                            const $progress = $section.find('.section-progress');
                            if ($progress.length) {
                                const totalTasks = $section.find('.child-task-checkbox').length;
                                const completedTasks = $section.find('.child-task-checkbox:checked').length;
                                $progress.text(completedTasks + '/' + totalTasks);
                            }
                        }

                        // Check if all tasks are completed
                        if (response.data.all_completed) {
                            $('.child-completion-message').addClass('show').slideDown(300);
                        } else {
                            $('.child-completion-message').removeClass('show').slideUp(300);
                        }
                    } else {
                        // Revert checkbox on error
                        $checkbox.prop('checked', !isChecked);
                        alert(response.data.message || 'Chyba pri aktualizácii');
                    }
                },
                error: function() {
                    // Revert checkbox on error
                    $checkbox.prop('checked', !isChecked);
                    alert('Chyba pri aktualizácii');
                },
                complete: function() {
                    // Re-enable checkbox
                    $checkbox.prop('disabled', false);
                    $taskItem.removeClass('updating');
                }
            });
        });
        
        // Handle reward purchase buttons
        const strings = rodinneUlohyFrontend.strings || {};
        
        function resetRewardButton($btn) {
            $btn.data('state', 'default');
            $btn.removeClass('reward-confirm');
            const defaultText = $btn.data('original-text') || strings.purchaseButton || 'Chcem';
            if (!$btn.prop('disabled')) {
                $btn.text(defaultText);
            }
        }
        
        $(document).on('click', '.reward-buy-btn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            if ($btn.prop('disabled')) {
                return;
            }
            
            const $card = $btn.closest('.reward-card');
            const cost = parseInt($card.data('cost') || 0, 10);
            const rewardId = parseInt($card.data('reward-id') || 0, 10);
            const rewardTitle = $card.data('reward-title') || '';
            const $overviewRoot = $card.closest('.rodinne-ulohy-child-overview');
            const childId = parseInt($overviewRoot.data('child-id') || 0, 10);
            
            if (!rewardId || !childId) {
                return;
            }
            
            const currentState = $btn.data('state') || 'default';
            
            if (currentState === 'confirm') {
                $btn.prop('disabled', true).text(strings.processing || '...');
                
                $.ajax({
                    url: rodinneUlohyFrontend.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'rodinne_ulohy_purchase_reward',
                        nonce: rodinneUlohyFrontend.nonce,
                        reward_id: rewardId,
                        child_id: childId
                    },
                    success: function(response) {
                        if (response.success) {
                            const data = response.data || {};
                            if (data.points_balance !== undefined) {
                                $overviewRoot.find('.points-number').text(data.points_balance);
                            }
                            if (data.active_counts) {
                                updateRewardStates($overviewRoot, data.active_counts, data.points_balance);
                            }
                        } else {
                            alert((response.data && response.data.message) || strings.purchaseError || 'Chyba pri nákupe odmeny.');
                        }
                    },
                    error: function() {
                        alert(strings.purchaseError || 'Chyba pri nákupe odmeny.');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                        resetRewardButton($btn);
                    }
                });
                return;
            }
            
            // Reset other buttons
            $('.reward-buy-btn').each(function() {
                const $other = $(this);
                if ($other.is($btn)) {
                    return;
                }
                if ($other.data('state') === 'confirm') {
                    resetRewardButton($other);
                }
            });
            
            const confirmTextTemplate = strings.confirmPurchase || 'Odpočíta sa ti %s bodov. OK';
            const confirmText = confirmTextTemplate.replace('%s', cost);
            $btn.data('original-text', $btn.text());
            $btn.data('state', 'confirm');
            $btn.addClass('reward-confirm');
            $btn.text(confirmText);
        });
        
        function updateRewardStates($root, activeCounts, currentBalance) {
            if (!$root || !$root.length) {
                return;
            }
            
            $root.find('.reward-card').each(function() {
                const $card = $(this);
                const rewardId = parseInt($card.data('reward-id') || 0, 10);
                const cost = parseInt($card.data('cost') || 0, 10);
                const $btn = $card.find('.reward-buy-btn');
                const affordable = currentBalance === undefined ? !$btn.prop('disabled') : (parseInt(currentBalance, 10) >= cost);
                
                if (currentBalance !== undefined) {
                    if (cost > parseInt(currentBalance, 10)) {
                        $card.addClass('reward-disabled');
                        $btn.prop('disabled', true).text(strings.notEnoughPoints || 'Máš málo bodov');
                    } else {
                        $card.removeClass('reward-disabled');
                        $btn.prop('disabled', false).text(strings.purchaseButton || 'Chcem');
                    }
                }
                
                // Get count - activeCounts can be object with count property or just number
                let countData = 0;
                if (activeCounts && activeCounts[rewardId] !== undefined) {
                    if (typeof activeCounts[rewardId] === 'object' && activeCounts[rewardId].count !== undefined) {
                        countData = parseInt(activeCounts[rewardId].count, 10);
                    } else {
                        countData = parseInt(activeCounts[rewardId], 10);
                    }
                }
                
                // Update badge
                let $badge = $card.find('.reward-purchased-badge');
                if (countData > 0) {
                    if (!$badge.length) {
                        $badge = $('<div class="reward-purchased-badge"></div>').prependTo($card);
                    }
                    $badge.text(countData + 'x');
                    $card.addClass('reward-purchased');
                } else {
                    if ($badge.length) {
                        $badge.remove();
                    }
                    $card.removeClass('reward-purchased');
                }
            });
        }
        
        // Check initial state on page load
        const $overview = $('.rodinne-ulohy-child-overview');
        if ($overview.length) {
            const $checkboxes = $overview.find('.child-task-checkbox');
            const total = $checkboxes.length;
            const checked = $checkboxes.filter(':checked').length;
            
            if (total > 0 && checked === total) {
                $('.child-completion-message').addClass('show');
            }
        }
        
        // Section switching
        $(document).on('click', '.footer-menu-item', function() {
            const $item = $(this);
            const section = $item.data('section');
            
            // Update active states
            $('.footer-menu-item').removeClass('active');
            $item.addClass('active');
            
            // Show/hide sections
            $('.child-section').removeClass('active');
            $('.child-section[data-section="' + section + '"]').addClass('active');
        });
        
        // Avatar upload (using WordPress media uploader if available)
        $(document).on('click', '#upload-avatar-frontend', function(e) {
            e.preventDefault();
            
            if (typeof wp === 'undefined' || !wp.media) {
                alert('Media uploader nie je dostupný. Prosím, použite admin rozhranie.');
                return;
            }
            
            const fileFrame = wp.media({
                title: 'Vyberte avatara',
                button: {
                    text: 'Použiť tento obrázok'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
            
            fileFrame.on('select', function() {
                const attachment = fileFrame.state().get('selection').first().toJSON();
                const avatarUrl = attachment.url;
                
                // Update preview
                const $previewImg = $('#settings-avatar-preview-img');
                const $placeholder = $('#settings-avatar-placeholder');
                
                if ($previewImg.length) {
                    $previewImg.attr('src', avatarUrl).show();
                    if ($placeholder.length) {
                        $placeholder.hide();
                    }
                } else if ($placeholder.length) {
                    // Create img if it doesn't exist
                    $placeholder.after('<img src="' + avatarUrl + '" id="settings-avatar-preview-img" style="width: 100%; height: 100%; object-fit: cover;">');
                    $placeholder.hide();
                }
                
                // Update hidden input
                $('#settings-avatar-url').val(avatarUrl);
                
                // Save to server
                const $overviewRoot = $('.rodinne-ulohy-child-overview');
                const childId = parseInt($overviewRoot.data('child-id') || 0, 10);
                
                if (!childId) {
                    alert('Chyba: ID dieťaťa nebolo nájdené.');
                    return;
                }
                
                $.ajax({
                    url: rodinneUlohyFrontend.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'rodinne_ulohy_save_child_avatar',
                        nonce: rodinneUlohyFrontend.nonce,
                        child_id: childId,
                        avatar_url: avatarUrl
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update header avatar
                            const $headerAvatar = $('.child-header-avatar');
                            if ($headerAvatar.find('img').length) {
                                $headerAvatar.find('img').attr('src', avatarUrl);
                            } else {
                                $headerAvatar.find('.child-avatar-placeholder').replaceWith(
                                    '<img src="' + avatarUrl + '" alt="" class="child-avatar-img">'
                                );
                            }
                        } else {
                            alert(response.data && response.data.message ? response.data.message : 'Chyba pri ukladaní avatara.');
                        }
                    },
                    error: function() {
                        alert('Chyba pri ukladaní avatara.');
                    }
                });
            });
            
            fileFrame.open();
        });
    });
    
})(jQuery);

