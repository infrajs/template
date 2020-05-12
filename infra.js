import { Controller } from '/vendor/infrajs/controller/src/Controller.js'

Controller.hand('init', async () => {
	await import('./init.js')
})