import Swal from 'sweetalert2';

window.addEventListener('lieu-enregistre', (event) => {
	const { icon = 'success', title = 'Lieu enregistré', text = '' } = event.detail ?? {};

	Swal.fire({
		icon,
		title,
		text,
		confirmButtonText: 'OK',
		confirmButtonColor: '#15663a',
	});
});
