// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A simple Javascript PubSub implementation.
 *
 * @module     core/pubsub
 * @copyright  2018 Ryan Wyllie <ryan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/pending'], function(Pending) {

    var events = {};

    /**
     * Subscribe to an event.
     *
     * @param {string} eventName The name of the event to subscribe to.
     * @param {function} callback The callback function to run when eventName occurs.
     */
    var subscribe = function(eventName, callback) {
        events[eventName] = events[eventName] || [];
        events[eventName].push(callback);
    };

    /**
     * Unsubscribe from an event.
     *
     * @param {string} eventName The name of the event to unsubscribe from.
     * @param {function} callback The callback to unsubscribe.
     */
    var unsubscribe = function(eventName, callback) {
        if (events[eventName]) {
            for (var i = 0; i < events[eventName].length; i++) {
                if (events[eventName][i] === callback) {
                    events[eventName].splice(i, 1);
                    break;
                }
            }
        }
    };

    /**
     * Publish an event to all subscribers.
     *
     * @param {string} eventName The name of the event to publish.
     * @param {any} data The data to provide to the subscribed callbacks.
     */
    var publish = function(eventName, data) {
        var pendingPromise = new Pending("Publishing " + eventName);
        if (events[eventName]) {
            events[eventName].forEach(function(callback) {
                callback(data);
            });
        }
        pendingPromise.resolve();
    };

    return {
        subscribe: subscribe,
        unsubscribe: unsubscribe,
        publish: publish
    };
});
