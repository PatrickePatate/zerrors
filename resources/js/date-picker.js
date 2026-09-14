// Backs <x-form.date-picker> — a Pines UI date picker
// (https://devdojo.com/pines/docs/date-picker) adapted to stay wireable the
// same way <x-form.select> is: the calendar drives a hidden native input via
// x-ref="hidden", and picking a day (or clearing the field) dispatches
// input/change on it so wire:model / wire:change work like a real input.

export default function datePicker(initialValue) {
    return {
        open: false,
        value: initialValue,
        month: 0,
        year: 0,
        daysInMonth: [],
        blankDaysInMonth: [],
        monthNames: [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December',
        ],
        days: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],

        init() {
            const current = this.value ? new Date(`${this.value}T00:00:00`) : new Date();
            this.month = current.getMonth();
            this.year = current.getFullYear();
            this.calculateDays();
        },

        formatDate(date) {
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');

            return `${date.getFullYear()}-${month}-${day}`;
        },

        get label() {
            if (!this.value) {
                return '';
            }

            const date = new Date(`${this.value}T00:00:00`);

            return date.toLocaleDateString(undefined, { dateStyle: 'medium' });
        },

        selectDay(day) {
            this.value = this.formatDate(new Date(this.year, this.month, day));
            this.open = false;
            this.$refs.hidden.value = this.value;
            this.$refs.hidden.dispatchEvent(new Event('input', { bubbles: true }));
            this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
        },

        isSelected(day) {
            return this.value === this.formatDate(new Date(this.year, this.month, day));
        },

        isToday(day) {
            return new Date().toDateString() === new Date(this.year, this.month, day).toDateString();
        },

        previousMonth() {
            if (this.month === 0) {
                this.month = 11;
                this.year--;
            } else {
                this.month--;
            }
            this.calculateDays();
        },

        nextMonth() {
            if (this.month === 11) {
                this.month = 0;
                this.year++;
            } else {
                this.month++;
            }
            this.calculateDays();
        },

        calculateDays() {
            const daysInMonth = new Date(this.year, this.month + 1, 0).getDate();
            const firstDayOfWeek = new Date(this.year, this.month, 1).getDay();

            this.blankDaysInMonth = Array.from({ length: firstDayOfWeek }, (_, i) => i);
            this.daysInMonth = Array.from({ length: daysInMonth }, (_, i) => i + 1);
        },
    };
}
