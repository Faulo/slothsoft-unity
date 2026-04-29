def runTests(def name = "tests") {
    catchError(stageResult: 'UNSTABLE', buildResult: 'UNSTABLE', catchInterruptions: false) {
        callShell 'composer update --prefer-lowest'

        dir('.reports') {
            deleteDir()
        }

        def report = ".reports/${name}.xml"

        catchError(stageResult: 'UNSTABLE', buildResult: 'UNSTABLE', catchInterruptions: false) {
            callShell "composer exec phpunit -- --log-junit ${report}"
        }

        if (fileExists(report)) {
            junit report
        }
    }
}

pipeline {
    agent none
    options {
        disableConcurrentBuilds()
        disableResume()
        disableRestartFromStage()
    }
    environment {
        COMPOSER_PROCESS_TIMEOUT = '3600'
        UNITY_CREDENTIALS = credentials('Slothsoft-Unity')
        EMAIL_CREDENTIALS = credentials('Slothsoft-Google')
        STEAM_CREDENTIALS = credentials('Slothsoft-Steam')
        EMAIL_TEST_TIME = '1745158411'
        EMAIL_TEST_CODE = '177824'
        UNITY_LOGGING = ''
    }
    stages {
        stage('Run Tests') {
            stage('Linux') {
                agent {
                    label 'unity && linux'
                }
                steps {
                    script {
                        runTests()
                    }
                }
            }
            stage('Windows') {
                agent {
                    label 'unity && windows'
                }
                steps {
                    script {
                        runTests()
                    }
                }
            }
        }
    }
}