// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * W1-3 (2026-05-15) — interactive star rating widget.
 *
 * Wires up any element with `data-airpay-rating` produced by
 * `\local_airpay_ratings\rating_manager::render()`. On click of a star, calls
 * the `local_airpay_ratings_submit_rating` WS and updates the display
 * optimistically (reverts on error). Dispatches `airpay:rating:changed` so
 * other widgets on the page (e.g. a global "X courses rated" counter) can
 * react.
 *
 * Page must include:
 *     $PAGE->requires->js_call_amd('local_airpay_ratings/rating_widget', 'init');
 *
 * @module     local_airpay_ratings/rating_widget
 */

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    'use strict';

    var STAR_FILLED  = 'fa fa-star';
    var STAR_HOLLOW  = 'fa fa-star-o';
    var COLOR_FILLED = '#efce2e';
    var COLOR_HOLLOW = '#9c9b97';

    /**
     * Wire up all rating widgets on the current page.
     */
    var init = function() {
        document.querySelectorAll('[data-airpay-rating]').forEach(setupWidget);
    };

    /**
     * Attach handlers to a single widget instance.
     * @param {HTMLElement} widget
     */
    function setupWidget(widget) {
        var itemid   = parseInt(widget.getAttribute('data-itemid'), 10);
        var ratearea = widget.getAttribute('data-ratearea');
        if (!itemid || !ratearea) {
            return;
        }

        // The "committed" rating we'll revert to if the click fails or the
        // user hovers and then leaves without clicking.
        var savedRating = parseInt(widget.getAttribute('data-my-rating') || '0', 10);

        var stars = widget.querySelectorAll('.airpay-rating__star');
        stars.forEach(function(btn) {
            // Hover preview — paint up to the hovered star.
            btn.addEventListener('mouseenter', function() {
                paint(widget, parseInt(btn.getAttribute('data-rating'), 10));
            });

            btn.addEventListener('click', function() {
                var newRating = parseInt(btn.getAttribute('data-rating'), 10);
                if (newRating < 1 || newRating > 5) {
                    return;
                }
                // Disable all stars until the server confirms.
                stars.forEach(function(s) { s.disabled = true; });
                paint(widget, newRating);

                Ajax.call([{
                    methodname: 'local_airpay_ratings_submit_rating',
                    args: {
                        itemid:   itemid,
                        ratearea: ratearea,
                        rating:   newRating
                    }
                }])[0].then(function(response) {
                    // Server-confirmed — bake in the new rating.
                    savedRating = newRating;
                    widget.setAttribute('data-my-rating', String(newRating));
                    var counttext = widget.querySelector('.airpay-rating__count');
                    if (counttext) {
                        counttext.textContent = '(' + response.average + ' / ' + response.count + ')';
                    }
                    // Notify any other listeners on the page.
                    document.dispatchEvent(new CustomEvent('airpay:rating:changed', {
                        detail: {
                            itemid:    itemid,
                            ratearea:  ratearea,
                            my_rating: newRating,
                            average:   response.average,
                            count:     response.count
                        }
                    }));
                    return null;
                }).catch(function(err) {
                    // Revert optimistic update.
                    paint(widget, savedRating);
                    Notification.exception(err);
                    return null;
                }).finally(function() {
                    stars.forEach(function(s) { s.disabled = false; });
                });
            });
        });

        // When the cursor leaves the whole widget, snap back to the saved rating.
        widget.addEventListener('mouseleave', function() {
            paint(widget, savedRating);
        });
    }

    /**
     * Repaint the stars to reflect a given rating value (1-5).
     * Stars whose data-rating <= value are filled; the rest are hollow.
     * @param {HTMLElement} widget
     * @param {number}      rating
     */
    function paint(widget, rating) {
        widget.querySelectorAll('.airpay-rating__star').forEach(function(btn) {
            var idx = parseInt(btn.getAttribute('data-rating'), 10);
            var icon = btn.querySelector('i');
            if (!icon) {
                return;
            }
            var filled = idx <= rating;
            icon.className   = filled ? STAR_FILLED  : STAR_HOLLOW;
            icon.style.color = filled ? COLOR_FILLED : COLOR_HOLLOW;
        });
    }

    return {
        init: init
    };
});
