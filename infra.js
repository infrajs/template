import { Controller } from '/vendor/infrajs/controller/src/Controller.js'

Controller.once('init', async () => {
	await import('./init.js')
})