/**
 * Sauna Booking – Frontend JS  v2.0.0
 * Calendar, time-slot loading, Stripe payment flow
 * Functionality identical to v1 — updated Stripe card element colours for light theme
 */
(function ($) {
  "use strict";

  /* ── State ───────────────────────────────────────────────── */
  var state = {
    productId: 0,
    priceEach: 0,
    calYear: 0,
    calMonth: 0,
    selectedDate: null,
    selectedSlot: null,
    bookingId: null,
    stripe: null,
    cardElement: null,
  };

  /* ── DOM refs ────────────────────────────────────────────── */
  var $calOverlay = $("#sbCalendarOverlay");
  var $bookOverlay = $("#sbBookingOverlay");
  var $calDays = $("#sbCalDays");
  var $monthLabel = $("#sbCalMonthLabel");
  var $slotsList = $("#sbSlotsList");
  var $slotsDateLbl = $("#sbSlotsDateLabel");
  var $bookingForm = $("#sbBookingForm");
  var $bookingSuccess = $("#sbBookingSuccess");
  var $payBtn = $("#sbPayBtn");
  var $payBtnText = $("#sbPayBtnText");
  var $payBtnSpinner = $("#sbPayBtnSpinner");
  var $amountTotal = $("#sbAmountTotal");
  var $cardErrors = $("#sbFormErrors");

  var MONTHS = [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December",
  ];
  var TODAY = new Date();

  /* ── Init ────────────────────────────────────────────────── */
  $(document).ready(function () {
    var $product = $(".sb-single-product");
    if (!$product.length) return;

    state.productId = parseInt($product.data("product-id"), 10);
    state.priceEach = parseFloat($product.data("price")) || 0;

    // Init calendar to today
    state.calYear = TODAY.getFullYear();
    state.calMonth = TODAY.getMonth();
    renderCalendar();

    // Stripe initialization removed - manual approval workflow implemented

    /* ── Event bindings ────────────────────────────────────── */
    $("#sbOpenCalendar").on("click", openCalendar);
    $(".sb-select-time-btn").on("click", openCalendar);

    $("#sbCloseCalendar").on("click", function () {
      $calOverlay.removeClass("active");
      $("body").removeClass("sb-overflow-hidden");
    });
    $("#sbCloseBooking").on("click", function () {
      $bookOverlay.removeClass("active");
      $("body").removeClass("sb-overflow-hidden");
    });

    $calOverlay.on("click", function (e) {
      if ($(e.target).is($calOverlay)) {
        $calOverlay.removeClass("active");
        $("body").removeClass("sb-overflow-hidden");
      }
    });
    $bookOverlay.on("click", function (e) {
      if ($(e.target).is($bookOverlay)) {
        $bookOverlay.removeClass("active");
        $("body").removeClass("sb-overflow-hidden");
      }
    });

    $("#sbCalPrev").on("click", function () {
      state.calMonth--;
      if (state.calMonth < 0) {
        state.calMonth = 11;
        state.calYear--;
      }
      renderCalendar();
    });
    $("#sbCalNext").on("click", function () {
      state.calMonth++;
      if (state.calMonth > 11) {
        state.calMonth = 0;
        state.calYear++;
      }
      renderCalendar();
    });

    // Booking type tabs — sync pill tabs with popup select
    $(".sb-type-btn").on("click", function () {
      $(".sb-type-btn").removeClass("active");
      $(this).addClass("active");
      var type = $(this).data("type");
      $("#sbTypeSelect").val(type);
      if (state.selectedDate) loadSlots(state.selectedDate);
    });

    // Popup type-select — sync back to pill tabs
    $("#sbTypeSelect").on("change", function () {
      var type = $(this).val();
      $(".sb-type-btn").removeClass("active");
      $('.sb-type-btn[data-type="' + type + '"]').addClass("active");
      if (state.selectedDate) loadSlots(state.selectedDate);
    });

    // Seats change → recalculate total
    $("#sbSeats").on("change", recalcTotal);

    // Form submit
    $bookingForm.on("submit", handlePayment);
  });

  /* ── Open calendar popup ─────────────────────────────────── */
  function openCalendar() {
    $calOverlay.addClass("active");
    $("body").addClass("sb-overflow-hidden");
  }

  /* ── Render calendar ─────────────────────────────────────── */
  function renderCalendar() {
    $monthLabel.text(MONTHS[state.calMonth] + " " + state.calYear);
    var firstDay = new Date(state.calYear, state.calMonth, 1).getDay();
    var daysInMonth = new Date(state.calYear, state.calMonth + 1, 0).getDate();

    $calDays.empty();

    // Empty cells before first day
    for (var i = 0; i < firstDay; i++) {
      $calDays.append('<div class="sb-cal-day empty"></div>');
    }

    for (var d = 1; d <= daysInMonth; d++) {
      var date = new Date(state.calYear, state.calMonth, d);
      var isPast =
        date < new Date(TODAY.getFullYear(), TODAY.getMonth(), TODAY.getDate());
      var isToday =
        d === TODAY.getDate() &&
        state.calMonth === TODAY.getMonth() &&
        state.calYear === TODAY.getFullYear();
      var dateStr = formatDate(state.calYear, state.calMonth + 1, d);
      var classes = ["sb-cal-day"];

      if (isPast) {
        classes.push("past");
      } else {
        classes.push("available", "has-slots");
      }
      if (isToday) classes.push("today");
      if (state.selectedDate === dateStr) classes.push("selected");

      var $day = $('<div class="' + classes.join(" ") + '">' + d + "</div>");
      if (!isPast) {
        $day.on(
          "click",
          (function (ds) {
            return function () {
              selectDate(ds);
            };
          })(dateStr),
        );
      }
      $calDays.append($day);
    }
  }

  /* ── Select date ─────────────────────────────────────────── */
  function selectDate(dateStr) {
    state.selectedDate = dateStr;
    state.selectedSlot = null;
    renderCalendar();
    loadSlots(dateStr);
  }

  /* ── Load time slots ─────────────────────────────────────── */
  function loadSlots(dateStr) {
    $slotsList.html('<div class="sb-loading"></div>');
    $slotsDateLbl.text("Loading…");

    $.ajax({
      url: SB_Data.ajax_url,
      type: "POST",
      data: {
        action: "sb_get_slots",
        nonce: SB_Data.nonce,
        product_id: state.productId,
        date: dateStr,
        type: $("#sbTypeSelect").val() || "early_bird",
      },
      success: function (res) {
        if (!res.success) {
          $slotsList.html(
            '<p class="sb-slots-placeholder">' +
              (res.data.message || "Error loading slots.") +
              "</p>",
          );
          return;
        }
        $slotsDateLbl.text(res.data.date_label);
        renderSlots(res.data.slots);
      },
      error: function () {
        $slotsList.html(
          '<p class="sb-slots-placeholder">Network error. Please try again.</p>',
        );
      },
    });
  }

  /* ── Render time slots ───────────────────────────────────── */
  function renderSlots(slots) {
    if (!slots || !slots.length) {
      $slotsList.html(
        '<p class="sb-slots-placeholder">No timeslots available for this date.</p>',
      );
      return;
    }
    $slotsList.empty();
    $.each(slots, function (i, slot) {
      var isFull = slot.available <= 0;
      var isSelected =
        state.selectedSlot && state.selectedSlot.start === slot.start;
      var classes =
        "sb-slot-item" +
        (isFull ? " full" : "") +
        (isSelected ? " selected" : "");
      var availText = isFull
        ? "Full"
        : slot.available + " / " + slot.total + " available";

      var $item = $(
        '<div class="' +
          classes +
          '" data-start="' +
          slot.start +
          '" data-end="' +
          slot.end +
          '">' +
          '<span class="sb-slot-time">' +
          slot.label +
          "</span>" +
          '<span class="sb-slot-avail">' +
          availText +
          "</span>" +
          "</div>",
      );
      if (!isFull) {
        $item.on("click", function () {
          selectSlot(slot);
        });
      }
      $slotsList.append($item);
    });
  }

  /* ── Select a slot → open booking form ──────────────────── */
  function selectSlot(slot) {
    state.selectedSlot = slot;

    // Update visual selection
    $(".sb-slot-item").removeClass("selected");
    $('.sb-slot-item[data-start="' + slot.start + '"]').addClass("selected");

    // Populate hidden fields
    $("#sbBookingDate").val(state.selectedDate);
    $("#sbSlotStart").val(slot.start);
    $("#sbSlotEnd").val(slot.end);

    // Build summary text
    var dateObj = new Date(state.selectedDate + "T00:00:00");
    var dateLabel = dateObj.toLocaleDateString("en-IE", {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
    });
    $("#sbBookingSummary").html(
      "<strong>" +
        $(".sb-detail-title").text() +
        "</strong><br>" +
        dateLabel +
        "<br>" +
        "<strong>Time:</strong> " +
        slot.label +
        "<br>" +
        "<strong>Available seats:</strong> " +
        slot.available,
    );

    // Update max seats dropdown
    var $seats = $("#sbSeats");
    var max = slot.available;
    $seats.empty();
    for (var i = 1; i <= max; i++) {
      $seats.append(
        '<option value="' +
          i +
          '">' +
          i +
          " seat" +
          (i > 1 ? "s" : "") +
          "</option>",
      );
    }
    recalcTotal();

    // Switch popups
    $calOverlay.removeClass("active");
    $bookingForm.show();
    $bookingSuccess.hide();
    $bookOverlay.addClass("active");
    $("body").addClass("sb-overflow-hidden");
  }

  /* ── Recalc total display ────────────────────────────────── */
  function recalcTotal() {
    var seats = parseInt($("#sbSeats").val(), 10) || 1;
    var total = (state.priceEach * seats).toFixed(2);
    $amountTotal.html(
      "Total: <span>" +
        SB_Data.currency_symbol +
        total +
        " " +
        SB_Data.currency +
        "</span>",
    ).hide();
  }

  /* ── Handle booking query submission ────────────────────── */
  function handlePayment(e) {
    e.preventDefault();

    var firstName = $.trim($("#sbFirstName").val());
    var lastName = $.trim($("#sbLastName").val());
    var email = $.trim($("#sbEmail").val());
    var phone = $.trim($("#sbPhone").val());
    var seats = parseInt($("#sbSeats").val(), 10);
    var notes = $.trim($("#sbNotes").val());

    if (!firstName || !lastName) {
      showFormError("Please enter your full name.");
      return;
    }
    if (!email || !isValidEmail(email)) {
      showFormError("Please enter a valid email address.");
      return;
    }

    setLoading(true);
    showFormError(""); 

    // Submit booking query via WP AJAX
    $.ajax({
      url: SB_Data.ajax_url,
      type: "POST",
      data: {
        action: "sb_submit_booking_query",
        nonce: SB_Data.nonce,
        product_id: state.productId,
        date: state.selectedDate,
        slot_start: state.selectedSlot.start,
        slot_end: state.selectedSlot.end,
        seats: seats,
        first_name: firstName,
        last_name: lastName,
        email: email,
        phone: phone,
        notes: notes,
      },
      success: function (res) {
        setLoading(false);
        if (!res.success) {
          showFormError(res.data.message || "Error submitting request.");
          return;
        }

        // Show success state
        $bookingForm.hide();
        $("#sbSuccessMessage").text(res.data.message);
        $bookingSuccess.show();
      },
      error: function () {
        showFormError("Network error. Please try again.");
        setLoading(false);
      },
    });
  }

  /* ── Helpers ─────────────────────────────────────────────── */
  function setLoading(on) {
    $payBtn.prop("disabled", on);
    $payBtnText.toggle(!on);
    $payBtnSpinner.toggle(on);
  }

  function showFormError(msg) {
    if (msg) {
      $cardErrors.text(msg).show();
      $cardErrors[0].scrollIntoView({ behavior: "smooth", block: "nearest" });
    } else {
      $cardErrors.hide().text("");
    }
  }

  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  function formatDate(y, m, d) {
    return (
      y + "-" + String(m).padStart(2, "0") + "-" + String(d).padStart(2, "0")
    );
  }
})(jQuery);
