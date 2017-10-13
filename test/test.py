#!/usr/bin/env python3

import subprocess
import sys
from os.path import abspath, dirname, join
from time import sleep, perf_counter

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC


class Test(object):

    def run(self, ip):
        self.br = webdriver.Chrome()
        self.br.get('http://' + ip)
        self.br.implicitly_wait(1)
        self.install()

    def install(self):
        self.fill('jform[site_name]', 'test')
        self.fill('jform[admin_email]', 'test@test.com')
        self.fill('jform[admin_user]', 'test')
        self.fill('jform[admin_password]', 'test')
        self.fill('jform[admin_password2]', 'test')
        self.click('a[title=Next]')

        self.fill('jform[db_host]', 'db')
        self.fill('jform[db_user]', 'root')
        self.fill('jform[db_name]', 'test')
        self.fill('jform[db_prefix]', 'test_')
        self.click('a[title=Next]')

        self.br.find_element_by_css_selector('.alert.alert-error')
        subprocess.run(['bash', '-c', "docker exec ${COMPOSE_PROJECT_NAME}_www_1 bash -c 'rm -f /var/www/html/installation/_Joomla*'"], check=True)

        self.click('a[title=Next]')

        self.click('a[title=Install]')

        self.click('[value="Remove installation folder"]', wait=30)
        self.wait_for('[value="Installation folder successfully removed."]')

        self.click('a[title=Administrator]')

        self.fill('username', 'test')
        self.fill('passwd', 'test')
        self.click('.login-button')

        self.click_text('Install VirtueMart with sample data')

        self.click('a.menu-install')

        self.br.execute_script('arguments[0].style.display = ""', self.wait_for('#legacy-uploader'))
        self.br.find_element_by_name('install_package').send_keys(
            abspath(join(dirname(__file__), '..', 'dist', 'pkg_webwinkelkeur.zip')))
        sleep(1)

        self.click_text('Components')
        self.click_text('WebwinkelKeur')

        self.fill('webwinkelkeur_wwk_shop_id', '1')
        self.fill('webwinkelkeur_wwk_api_key', 'abcd')
        self.click('[name=webwinkelkeur_invite]')
        self.click('.button-apply')

        self.click('a[title="Preview test"]')

        self.focus_tab()
        self.wait_for('.wwk--sidebar')

    def fill(self, name, value):
        el = self.br.find_element_by_name(name)
        self.br.execute_script('''
            arguments[0].value = arguments[1]

            var e = document.createEvent('HTMLEvents')
            e.initEvent('change', false, true)
            arguments[0].dispatchEvent(e)
        ''', el, str(value))

    def click(self, css_selector, **kwargs):
        self.click_by(By.CSS_SELECTOR, css_selector, **kwargs)

    def click_text(self, text, **kwargs):
        self.click_by(By.PARTIAL_LINK_TEXT, text, **kwargs)

    def wait_for(self, css_selector, wait=30):
        return self.wait_by(By.CSS_SELECTOR, css_selector, wait=wait)

    def click_by(self, *by, wait=1):
        el = self.wait_by(*by, wait=wait)
        self.br.execute_script('arguments[0].click()', el)

    def wait_by(self, *by, wait=1):
        return WebDriverWait(self.br, wait).until(EC.presence_of_element_located(by))

    def focus_tab(self):
        wait_until = perf_counter() + 5
        while len(self.br.window_handles) < 2:
            if perf_counter() > wait_until:
                raise RuntimeError("Waiting for a new tab, but none found")
            sleep(0.1)
        self.br.switch_to_window(self.br.window_handles[-1])


if __name__ == '__main__':

    Test().run(*sys.argv[1:])
