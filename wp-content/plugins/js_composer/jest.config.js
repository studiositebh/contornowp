module.exports = {
	testMatch: [
		'<rootDir>/tests/js/**/*.test.js'
	],
	testEnvironment: 'jsdom',
	projects: [
		{
			displayName: 'Unit Tests',
			testMatch: [
				'<rootDir>/tests/js/unit/**/*.test.js'
			],
			testEnvironment: 'jsdom',
			setupFilesAfterEnv: [
				'<rootDir>/tests/js/setup.integration.js'
			]
		},
		{
			displayName: 'Integration Tests',
			testMatch: [
				'<rootDir>/tests/js/integration/**/*.test.js'
			],
			testEnvironment: 'jsdom',
			setupFilesAfterEnv: [
				'<rootDir>/tests/js/setup.integration.js'
			]
		}
	]
};
