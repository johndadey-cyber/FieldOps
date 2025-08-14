// google_address_autocomplete.js
// ✅ Updated: Aug 6, 2025 – For FieldOps Customer Form (add/edit)

function initializeAddressAutocomplete(inputId, fieldMap = {}) {
    const input = document.getElementById(inputId);
    if (!input) {
        console.warn(`Address input field with ID '${inputId}' not found.`);
        return;
    }

    const autocomplete = new google.maps.places.Autocomplete(input, {
        types: ['address'],
        componentRestrictions: { country: 'us' },
        fields: ['address_components', 'geometry', 'place_id']
    });

    autocomplete.addListener('place_changed', function () {
        const place = autocomplete.getPlace();

        if (!place.address_components) {
            console.warn("No address components found for selected place.");
            return;
        }

        const components = {};
        place.address_components.forEach(component => {
            const type = component.types[0];
            components[type] = component.long_name;
        });

        const assign = (id, value) => {
            const field = document.getElementById(fieldMap[id]);
            if (field) field.value = value || '';
        };

        assign('address_line1', input.value);
        assign('address_line2', ''); // Left for manual entry
        assign('city', components.locality || components.sublocality || '');
        assign('state', components.administrative_area_level_1 || '');
        assign('postal_code', components.postal_code || '');
        assign('country', components.country || '');

        if (place.geometry && place.geometry.location) {
            assign('latitude', place.geometry.location.lat());
            assign('longitude', place.geometry.location.lng());
        }

        assign('google_place_id', place.place_id || '');
    });
}
