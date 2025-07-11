const daysGrid = document.getElementById("calendar-days");
const monthYearLabel = document.getElementById("month-year");
const transactionDate = document.getElementById("transaction-date");

const prevBtn = document.getElementById("prev");
const nextBtn = document.getElementById("next");

let currentDate = new Date();

//function to optionally include time
function formatDate(date, includeTime = false) {
  return date.toLocaleString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    ...(includeTime && { hour: '2-digit', minute: '2-digit', hour12: false }),
  });
}

//SET INITIAL TRANSACTION DATE TO TODAY WITH TIME
transactionDate.textContent = formatDate(new Date(), true);

function renderCalendar(date) {
  const year = date.getFullYear();
  const month = date.getMonth();
  const today = new Date();

  const firstDay = new Date(year, month, 1);
  const startDay = firstDay.getDay(); // start on Sunday
  const lastDate = new Date(year, month + 1, 0).getDate();
  const prevMonthLastDate = new Date(year, month, 0).getDate();

  monthYearLabel.textContent = `${date.toLocaleString("default", {
    month: "long",
  })} ${year}`;

  daysGrid.innerHTML = "";

  // Previous month's trailing days
  for (let i = startDay; i > 0; i--) {
    const day = document.createElement("div");
    day.classList.add("faded");
    day.textContent = prevMonthLastDate - i + 1;
    daysGrid.appendChild(day);
  }

  // Current month days
  for (let i = 1; i <= lastDate; i++) {
    const day = document.createElement("div");
    day.textContent = i;

    const isToday =
      i === today.getDate() &&
      month === today.getMonth() &&
      year === today.getFullYear();

    if (isToday) {
      day.classList.add("today");
    }

    day.addEventListener("click", () => {
      document
        .querySelectorAll(".calendar-grid div")
        .forEach(el => el.classList.remove("selected"));
      day.classList.add("selected");

      const selectedDate = new Date(year, month, i);

      //IF TODAY, INCLUDE TIME; ELSE, OMIT TIME
      if (isToday) {
        // Include current time
        const now = new Date();
        selectedDate.setHours(now.getHours(), now.getMinutes());
        transactionDate.textContent = formatDate(selectedDate, true);
      } else {
        // No time for other dates
        transactionDate.textContent = formatDate(selectedDate, false);
      }
    });

    daysGrid.appendChild(day);
  }

  // Next month's leading days
  const totalFilled = startDay + lastDate;
  const extraDays = (7 - (totalFilled % 7)) % 7;

  for (let i = 1; i <= extraDays; i++) {
    const day = document.createElement("div");
    day.classList.add("faded");
    day.textContent = i;
    daysGrid.appendChild(day);
  }
}

prevBtn.addEventListener("click", () => {
  currentDate.setMonth(currentDate.getMonth() - 1);
  renderCalendar(currentDate);
});

nextBtn.addEventListener("click", () => {
  currentDate.setMonth(currentDate.getMonth() + 1);
  renderCalendar(currentDate);
});

// Initial render
renderCalendar(currentDate);
