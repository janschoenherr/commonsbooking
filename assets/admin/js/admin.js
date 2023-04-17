(function($) {
    "use strict";
    $(function() {
        if ($("#holiday_load_btn").length) {
            var fillHolidays = (year, state) => {
                var holidays = feiertagejs.getHolidays(year, state);
                const inputField = $("#timeframe_manual_date");
                const DATE_SEPERATOR = ",";
                holidays.forEach(holiday => {
                    var date = new Date(holiday.date);
                    var dd = date.getDate().length == 1 ? "0" + date.getDate() : date.getDate();
                    var mm = (date.getMonth() + 1).length == 1 ? "0" + (date.getMonth() + 1) : date.getMonth() + 1;
                    var yyyy = date.getFullYear();
                    var dateStr = yyyy + "-" + mm + "-" + dd;
                    if (inputField.val().length > 0) {
                        if (inputField.val().slice(-1) !== DATE_SEPERATOR) {
                            inputField.val(inputField.val() + DATE_SEPERATOR + dateStr);
                        } else {
                            inputField.val(inputField.val() + dateStr);
                        }
                    } else {
                        inputField.val(dateStr + DATE_SEPERATOR);
                    }
                });
            };
            $("#holiday_load_btn").click(function() {
                fillHolidays($("#_cmb2_holidayholiday_year").val(), $("#_cmb2_holidayholiday_state").val());
            });
        }
    });
})(jQuery);

(function($) {
    "use strict";
    $(function() {
        $("#cmb2-metabox-migration #migration-start").on("click", function(event) {
            event.preventDefault();
            $("#migration-state").show();
            $("#migration-in-progress").show();
            const runMigration = data => {
                $.post(cb_ajax_start_migration.ajax_url, {
                    _ajax_nonce: cb_ajax_start_migration.nonce,
                    action: "cb_start_migration",
                    data: data,
                    geodata: $("#get-geo-locations").is(":checked")
                }, function(data) {
                    let allComplete = true;
                    $.each(data, function(index, value) {
                        $("#" + index + "-index").text(value.index);
                        $("#" + index + "-count").text(value.count);
                        if (value.complete == "0") {
                            allComplete = false;
                        }
                    });
                    if (!allComplete) {
                        runMigration(data);
                    } else {
                        $("#migration-in-progress").hide();
                        $("#migration-done").show();
                    }
                });
            };
            runMigration(false);
        });
        $("#cmb2-metabox-migration #booking-update-start").on("click", function(event) {
            event.preventDefault();
            $("#booking-migration-in-progress").show();
            $.post(cb_ajax_start_migration.ajax_url, {
                _ajax_nonce: cb_ajax_start_migration.nonce,
                action: "cb_start_booking_migration"
            }).done(function() {
                $("#booking-migration-in-progress").hide();
                $("#booking-migration-done").show();
            }).fail(function() {
                $("#booking-migration-in-progress").hide();
                $("#booking-migration-failed").show();
            });
        });
    });
})(jQuery);

(function($) {
    "use strict";
    $(function() {
        const form = $("input[name=post_type][value=cb_restriction]").parent("form");
        form.find("input, select, textarea").on("keyup change paste", function() {
            form.find("input[name=restriction-send]").prop("disabled", true);
        });
    });
})(jQuery);

(function($) {
    "use strict";
    $(function() {
        const hideFieldset = function(set) {
            $.each(set, function() {
                $(this).parents(".cmb-row").hide();
            });
        };
        const showFieldset = function(set) {
            $.each(set, function() {
                $(this).parents(".cmb-row").show();
            });
        };
        const emailform = $("#templates");
        if (emailform.length) {
            const eventCreateCheckbox = $("#emailtemplates_mail-booking_ics_attach");
            const eventTitleInput = $("#emailtemplates_mail-booking_ics_event-title");
            const eventDescInput = $("#emailtemplates_mail-booking_ics_event-description");
            const eventFieldSet = [ eventTitleInput, eventDescInput ];
            const handleiCalAttachmentSelection = function() {
                showFieldset(eventFieldSet);
                if (!eventCreateCheckbox.prop("checked")) {
                    hideFieldset(eventFieldSet);
                    eventCreateCheckbox.prop("checked", false);
                }
            };
            handleiCalAttachmentSelection();
            eventCreateCheckbox.click(function() {
                handleiCalAttachmentSelection();
            });
        }
    });
})(jQuery);

(function($) {
    "use strict";
    $(function() {
        const arrayDiff = function(array1, array2) {
            var newItems = [];
            jQuery.grep(array2, function(i) {
                if (jQuery.inArray(i, array1) == -1) {
                    newItems.push(i);
                }
            });
            return newItems;
        };
        const hideFieldset = function(set) {
            $.each(set, function() {
                $(this).parents(".cmb-row").hide();
            });
        };
        const showFieldset = function(set) {
            $.each(set, function() {
                $(this).parents(".cmb-row").show();
            });
        };
        const timeframeForm = $("#cmb2-metabox-cb_timeframe-custom-fields");
        if (timeframeForm.length) {
            const timeframeRepetitionInput = $("#timeframe-repetition");
            const typeInput = $("#type");
            const gridInput = $("#grid");
            const weekdaysInput = $("#weekdays1");
            const startTimeInput = $("#start-time");
            const endTimeInput = $("#end-time");
            const repConfigTitle = $("#title-timeframe-rep-config");
            const repetitionStartInput = $("#repetition-start");
            const repetitionEndInput = $("#repetition-end");
            const fullDayInput = $("#full-day");
            const bookingCodeTitle = $("#title-timeframe-booking-codes");
            const showBookingCodes = $("#show-booking-codes");
            const createBookingCodesInput = $("#create-booking-codes");
            const bookingCodesDownload = $("#booking-codes-download");
            const bookingCodesList = $("#booking-codes-list");
            const holidayField = $(".cmb2-id--cmb2-holiday");
            const holidayInput = $("#timeframe_manual_date");
            const manualDateField = $(".cmb2-id-timeframe-manual-date");
            const maxDaysSelect = $(".cmb2-id-timeframe-max-days");
            const advanceBookingDays = $(".cmb2-id-timeframe-advance-booking-days");
            const allowUserRoles = $(".cmb2-id-allowed-user-roles");
            const repSet = [ repConfigTitle, fullDayInput, startTimeInput, endTimeInput, weekdaysInput, repetitionStartInput, repetitionEndInput, gridInput ];
            const noRepSet = [ fullDayInput, startTimeInput, endTimeInput, gridInput, repetitionStartInput, repetitionEndInput ];
            const repTimeFieldsSet = [ gridInput, startTimeInput, endTimeInput ];
            const bookingCodeSet = [ createBookingCodesInput, bookingCodesList, bookingCodesDownload, showBookingCodes ];
            const showRepFields = function() {
                showFieldset(repSet);
                hideFieldset(arrayDiff(repSet, noRepSet));
            };
            const showNoRepFields = function() {
                showFieldset(noRepSet);
                hideFieldset(arrayDiff(noRepSet, repSet));
            };
            const uncheck = function(checkboxes) {
                $.each(checkboxes, function() {
                    $(this).prop("checked", false);
                });
            };
            const handleTypeSelection = function() {
                const selectedType = $("option:selected", typeInput).val();
                const selectedRepetition = $("option:selected", timeframeRepetitionInput).val();
                if (selectedType == 2) {
                    maxDaysSelect.show();
                    advanceBookingDays.show();
                    allowUserRoles.show();
                    showFieldset(bookingCodeTitle);
                } else {
                    maxDaysSelect.hide();
                    advanceBookingDays.hide();
                    allowUserRoles.hide();
                    hideFieldset(bookingCodeTitle);
                    if (selectedType == 3 && selectedRepetition == "manual") {
                        holidayField.show();
                    } else {
                        holidayField.hide();
                        holidayInput.val("");
                    }
                }
            };
            handleTypeSelection();
            typeInput.change(function() {
                handleTypeSelection();
            });
            const handleRepititionSelection = function() {
                const selectedRepetition = $("option:selected", timeframeRepetitionInput).val();
                const selectedType = $("option:selected", typeInput).val();
                if (selectedRepetition !== "manual") {
                    manualDateField.hide();
                    holidayField.hide();
                    holidayInput.val("");
                } else {
                    manualDateField.show();
                    if (selectedType == 3) {
                        holidayField.show();
                    } else {
                        holidayField.hide();
                        holidayInput.val("");
                    }
                }
            };
            handleRepititionSelection();
            timeframeRepetitionInput.change(function() {
                handleRepititionSelection();
            });
            const handleFullDaySelection = function() {
                const selectedRep = $("option:selected", timeframeRepetitionInput).val();
                if (fullDayInput.prop("checked")) {
                    gridInput.prop("selected", false);
                    hideFieldset(repTimeFieldsSet);
                } else {
                    showFieldset(repTimeFieldsSet);
                }
            };
            handleFullDaySelection();
            fullDayInput.change(function() {
                handleFullDaySelection();
            });
            const handleRepetitionSelection = function() {
                const selectedType = $("option:selected", timeframeRepetitionInput).val();
                const selectedTimeframeType = $("option:selected", typeInput).val();
                if (selectedType) {
                    if (selectedType == "norep") {
                        showNoRepFields();
                    } else {
                        showRepFields();
                    }
                    if (selectedType == "manual") {
                        manualDateField.show();
                        hideFieldset(repetitionStartInput);
                        hideFieldset(repetitionEndInput);
                    } else {
                        manualDateField.hide();
                        showFieldset(repetitionStartInput);
                        showFieldset(repetitionEndInput);
                    }
                    if (selectedType == "w") {
                        weekdaysInput.parents(".cmb-row").show();
                    } else {
                        weekdaysInput.parents(".cmb-row").hide();
                        uncheck($("input[name*=weekdays]"));
                    }
                    handleFullDaySelection();
                } else {
                    hideFieldset(noRepSet);
                    hideFieldset(repSet);
                }
            };
            handleRepetitionSelection();
            timeframeRepetitionInput.change(function() {
                handleRepetitionSelection();
            });
            const handleBookingCodesSelection = function() {
                const fullday = fullDayInput.prop("checked"), type = typeInput.val(), repStart = repetitionStartInput.val(), repEnd = repetitionEndInput.val();
                hideFieldset(bookingCodeSet);
                if (repStart && repEnd && fullday && type == 2) {
                    showFieldset(bookingCodeSet);
                    if (!createBookingCodesInput.prop("checked")) {
                        hideFieldset([ showBookingCodes ]);
                        showBookingCodes.prop("checked", false);
                    }
                }
            };
            handleBookingCodesSelection();
            const bookingCodeSelectionInputs = [ repetitionStartInput, repetitionEndInput, fullDayInput, typeInput, createBookingCodesInput ];
            $.each(bookingCodeSelectionInputs, function(key, input) {
                input.change(function() {
                    handleBookingCodesSelection();
                });
            });
        }
    });
})(jQuery);

(function($) {
    "use strict";
    $(function() {
        $(document).tooltip();
    });
})(jQuery);

(function(global, factory) {
    typeof exports === "object" && typeof module !== "undefined" ? factory(exports) : typeof define === "function" && define.amd ? define([ "exports" ], factory) : (global = typeof globalThis !== "undefined" ? globalThis : global || self, 
    function() {
        var current = global.feiertagejs;
        var exports = global.feiertagejs = {};
        factory(exports);
        exports.noConflict = function() {
            global.feiertagejs = current;
            return exports;
        };
    }());
})(this, function(exports) {
    "use strict";
    const germanTranslations = {
        NEUJAHRSTAG: "Neujahrstag",
        HEILIGEDREIKOENIGE: "Heilige Drei Könige",
        KARFREITAG: "Karfreitag",
        OSTERSONNTAG: "Ostersonntag",
        OSTERMONTAG: "Ostermontag",
        TAG_DER_ARBEIT: "Tag der Arbeit",
        CHRISTIHIMMELFAHRT: "Christi Himmelfahrt",
        PFINGSTSONNTAG: "Pfingstsonntag",
        PFINGSTMONTAG: "Pfingstmontag",
        FRONLEICHNAM: "Fronleichnam",
        MARIAHIMMELFAHRT: "Mariä Himmelfahrt",
        DEUTSCHEEINHEIT: "Tag der Deutschen Einheit",
        REFORMATIONSTAG: "Reformationstag",
        ALLERHEILIGEN: "Allerheiligen",
        BUBETAG: "Buß- und Bettag",
        ERSTERWEIHNACHTSFEIERTAG: "1. Weihnachtstag",
        ZWEITERWEIHNACHTSFEIERTAG: "2. Weihnachtstag",
        WELTKINDERTAG: "Weltkindertag",
        WELTFRAUENTAG: "Weltfrauentag",
        AUGSBURGER_FRIEDENSFEST: "Augsburger Friedensfest"
    };
    const allHolidays = [ "NEUJAHRSTAG", "HEILIGEDREIKOENIGE", "KARFREITAG", "OSTERSONNTAG", "OSTERMONTAG", "TAG_DER_ARBEIT", "CHRISTIHIMMELFAHRT", "MARIAHIMMELFAHRT", "PFINGSTSONNTAG", "PFINGSTMONTAG", "FRONLEICHNAM", "DEUTSCHEEINHEIT", "REFORMATIONSTAG", "ALLERHEILIGEN", "BUBETAG", "ERSTERWEIHNACHTSFEIERTAG", "ZWEITERWEIHNACHTSFEIERTAG", "WELTKINDERTAG", "WELTFRAUENTAG", "AUGSBURGER_FRIEDENSFEST" ];
    const allRegions = [ "BW", "BY", "BE", "BB", "HB", "HE", "HH", "MV", "NI", "NW", "RP", "SL", "SN", "ST", "SH", "TH", "BUND", "AUGSBURG", "ALL" ];
    const defaultLanguage = "de";
    let currentLanguage = defaultLanguage;
    const translations = {
        de: germanTranslations
    };
    function addTranslation(isoCode, newTranslation) {
        const code = isoCode.toLowerCase();
        const defaultTranslation = translations[defaultLanguage];
        let missingFields = false;
        for (const holiday of allHolidays) {
            if (!newTranslation[holiday]) {
                missingFields = true;
                newTranslation[holiday] = defaultTranslation[holiday];
            }
        }
        if (missingFields) {
            console.warn("[feiertagejs] addTranslation: you did not add all holidays in your translation! Took German as fallback");
        }
        translations[code] = newTranslation;
    }
    function setLanguage(isoCode) {
        const code = isoCode.toLowerCase();
        if (!translations[code]) {
            throw new TypeError(`[feiertagejs] tried to set language to ${code} but the translation is missing. Please use addTranslation(isoCode,object) first`);
        }
        currentLanguage = isoCode;
    }
    function getLanguage() {
        return currentLanguage;
    }
    function isSunOrHoliday(date, region) {
        checkRegion(region);
        return date.getDay() === 0 || isHoliday(date, region);
    }
    function isHoliday(date, region) {
        checkRegion(region);
        const year = date.getFullYear();
        const internalDate = toUtcTimestamp(date);
        const holidays = getHolidaysAsUtcTimestamps(year, region);
        return holidays.indexOf(internalDate) !== -1;
    }
    function getHolidayByDate(date, region = "ALL") {
        checkRegion(region);
        const holidays = getHolidaysOfYear(date.getFullYear(), region);
        return holidays.find(holiday => holiday.equals(date));
    }
    function checkRegion(region) {
        if (region === null || region === undefined) {
            throw new Error(`Region must not be undefined or null`);
        }
        if (allRegions.indexOf(region) === -1) {
            throw new Error(`Invalid region: ${region}! Must be one of ${allRegions.toString()}`);
        }
    }
    function checkHolidayType(holidayName) {
        if (holidayName === null || holidayName === undefined) {
            throw new TypeError("holidayName must not be null or undefined");
        }
        if (allHolidays.indexOf(holidayName) === -1) {
            throw new Error(`feiertage.js: invalid holiday type "${holidayName}"! Must be one of ${allHolidays.toString()}`);
        }
    }
    function isSpecificHoliday(date, holidayName, region = "ALL") {
        checkRegion(region);
        checkHolidayType(holidayName);
        const holidays = getHolidaysOfYear(date.getFullYear(), region);
        const foundHoliday = holidays.find(holiday => holiday.equals(date));
        if (!foundHoliday) {
            return false;
        }
        return foundHoliday.name === holidayName;
    }
    function getHolidays(year, region) {
        let y;
        if (typeof year === "string") {
            y = parseInt(year, 10);
        } else {
            y = year;
        }
        checkRegion(region);
        return getHolidaysOfYear(y, region);
    }
    function getHolidaysAsUtcTimestamps(year, region) {
        const holidays = getHolidaysOfYear(year, region);
        return holidays.map(holiday => toUtcTimestamp(holiday.date));
    }
    function getHolidaysOfYear(year, region) {
        const easterDate = getEasterDate(year);
        const karfreitag = addDays(new Date(easterDate.getTime()), -2);
        const ostermontag = addDays(new Date(easterDate.getTime()), 1);
        const christiHimmelfahrt = addDays(new Date(easterDate.getTime()), 39);
        const pfingstsonntag = addDays(new Date(easterDate.getTime()), 49);
        const pfingstmontag = addDays(new Date(easterDate.getTime()), 50);
        const holidays = [ ...getCommonHolidays(year), newHoliday("KARFREITAG", karfreitag), newHoliday("OSTERMONTAG", ostermontag), newHoliday("CHRISTIHIMMELFAHRT", christiHimmelfahrt), newHoliday("PFINGSTMONTAG", pfingstmontag) ];
        addHeiligeDreiKoenige(year, region, holidays);
        addEasterAndPfingsten(year, region, easterDate, pfingstsonntag, holidays);
        addFronleichnam(region, easterDate, holidays);
        addMariaeHimmelfahrt(year, region, holidays);
        addReformationstag(year, region, holidays);
        addAllerheiligen(year, region, holidays);
        addBussUndBetttag(year, region, holidays);
        addWeltkindertag(year, region, holidays);
        addWeltfrauenTag(year, region, holidays);
        addRegionalHolidays(year, region, holidays);
        return holidays.sort((a, b) => a.date.getTime() - b.date.getTime());
    }
    function getCommonHolidays(year) {
        return [ newHoliday("NEUJAHRSTAG", makeDate(year, 1, 1)), newHoliday("TAG_DER_ARBEIT", makeDate(year, 5, 1)), newHoliday("DEUTSCHEEINHEIT", makeDate(year, 10, 3)), newHoliday("ERSTERWEIHNACHTSFEIERTAG", makeDate(year, 12, 25)), newHoliday("ZWEITERWEIHNACHTSFEIERTAG", makeDate(year, 12, 26)) ];
    }
    function addRegionalHolidays(year, region, feiertageObjects) {
        if (region === "AUGSBURG") {
            feiertageObjects.push(newHoliday("AUGSBURGER_FRIEDENSFEST", makeDate(year, 8, 8)));
        }
    }
    function addHeiligeDreiKoenige(year, region, feiertageObjects) {
        if (region === "BW" || region === "BY" || region === "AUGSBURG" || region === "ST" || region === "ALL") {
            feiertageObjects.push(newHoliday("HEILIGEDREIKOENIGE", makeDate(year, 1, 6)));
        }
    }
    function addEasterAndPfingsten(year, region, easterDate, pfingstsonntag, feiertageObjects) {
        if (region === "BB" || region === "ALL") {
            feiertageObjects.push(newHoliday("OSTERSONNTAG", easterDate), newHoliday("PFINGSTSONNTAG", pfingstsonntag));
        }
    }
    function addFronleichnam(region, easterDate, holidays) {
        if (region === "BW" || region === "BY" || region === "AUGSBURG" || region === "HE" || region === "NW" || region === "RP" || region === "SL" || region === "ALL") {
            const fronleichnam = addDays(new Date(easterDate.getTime()), 60);
            holidays.push(newHoliday("FRONLEICHNAM", fronleichnam));
        }
    }
    function addMariaeHimmelfahrt(year, region, holidays) {
        if (region === "SL" || region === "BY" || region === "AUGSBURG" || region === "ALL") {
            holidays.push(newHoliday("MARIAHIMMELFAHRT", makeDate(year, 8, 15)));
        }
    }
    function addReformationstag(year, region, holidays) {
        if (year === 2017 || region === "NI" || region === "BB" || region === "HB" || region === "HH" || region === "MV" || region === "SN" || region === "ST" || region === "TH" || region === "SH" || region === "ALL") {
            holidays.push(newHoliday("REFORMATIONSTAG", makeDate(year, 10, 31)));
        }
    }
    function addAllerheiligen(year, region, holidays) {
        if (region === "BW" || region === "BY" || region === "AUGSBURG" || region === "NW" || region === "RP" || region === "SL" || region === "ALL") {
            holidays.push(newHoliday("ALLERHEILIGEN", makeDate(year, 11, 1)));
        }
    }
    function addBussUndBetttag(year, region, holidays) {
        if (region === "SN" || region === "ALL") {
            const bussbettag = getBussBettag(year);
            holidays.push(newHoliday("BUBETAG", makeDate(bussbettag.getUTCFullYear(), bussbettag.getUTCMonth() + 1, bussbettag.getUTCDate())));
        }
    }
    function addWeltkindertag(year, region, holidays) {
        if (year >= 2019 && (region === "TH" || region === "ALL")) {
            holidays.push(newHoliday("WELTKINDERTAG", makeDate(year, 9, 20)));
        }
    }
    function addWeltfrauenTag(year, region, feiertageObjects) {
        if (year <= 2018) {
            return;
        }
        if (region === "BE" || region === "ALL") {
            feiertageObjects.push(newHoliday("WELTFRAUENTAG", makeDate(year, 3, 8)));
        }
        if (region === "MV" && year >= 2023) {
            feiertageObjects.push(newHoliday("WELTFRAUENTAG", makeDate(year, 3, 8)));
        }
    }
    function getEasterDate(year) {
        const C = Math.floor(year / 100);
        const N = year - 19 * Math.floor(year / 19);
        const K = Math.floor((C - 17) / 25);
        let I = C - Math.floor(C / 4) - Math.floor((C - K) / 3) + 19 * N + 15;
        I -= 30 * Math.floor(I / 30);
        I -= Math.floor(I / 28) * (1 - Math.floor(I / 28) * Math.floor(29 / (I + 1)) * Math.floor((21 - N) / 11));
        let J = year + Math.floor(year / 4) + I + 2 - C + Math.floor(C / 4);
        J -= 7 * Math.floor(J / 7);
        const L = I - J;
        const M = 3 + Math.floor((L + 40) / 44);
        const D = L + 28 - 31 * Math.floor(M / 4);
        return new Date(year, M - 1, D);
    }
    function getBussBettag(jahr) {
        const weihnachten = new Date(jahr, 11, 25, 12, 0, 0);
        const ersterAdventOffset = 32;
        let wochenTagOffset = weihnachten.getDay() % 7;
        if (wochenTagOffset === 0) {
            wochenTagOffset = 7;
        }
        const tageVorWeihnachten = wochenTagOffset + ersterAdventOffset;
        let bbtag = new Date(weihnachten.getTime());
        bbtag = addDays(bbtag, -tageVorWeihnachten);
        return bbtag;
    }
    function addDays(date, days) {
        const changedDate = new Date(date);
        changedDate.setDate(date.getDate() + days);
        return changedDate;
    }
    function makeDate(year, naturalMonth, day) {
        return new Date(year, naturalMonth - 1, day);
    }
    function newHoliday(name, date) {
        return {
            name: name,
            date: date,
            dateString: localeDateObjectToDateString(date),
            trans(lang = currentLanguage) {
                console.warn('FeiertageJs: You are using "Holiday.trans() method. This will be replaced in the next major version with translate()"');
                return this.translate(lang);
            },
            translate(lang = currentLanguage) {
                return lang === undefined || lang === null ? undefined : translations[lang][this.name];
            },
            getNormalizedDate() {
                return toUtcTimestamp(this.date);
            },
            equals(otherDate) {
                const dateString = localeDateObjectToDateString(otherDate);
                return this.dateString === dateString;
            }
        };
    }
    function localeDateObjectToDateString(date) {
        const normalizedDate = new Date(date.getTime() - date.getTimezoneOffset() * 60 * 1e3);
        normalizedDate.setUTCHours(0, 0, 0, 0);
        return normalizedDate.toISOString().slice(0, 10);
    }
    function toUtcTimestamp(date) {
        const internalDate = new Date(date);
        internalDate.setHours(0, 0, 0, 0);
        return internalDate.getTime();
    }
    exports.addTranslation = addTranslation;
    exports.getHolidayByDate = getHolidayByDate;
    exports.getHolidays = getHolidays;
    exports.getLanguage = getLanguage;
    exports.isHoliday = isHoliday;
    exports.isSpecificHoliday = isSpecificHoliday;
    exports.isSunOrHoliday = isSunOrHoliday;
    exports.setLanguage = setLanguage;
});