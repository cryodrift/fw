// need this to show and hide d-flex flex-grow-1 columns, not sure why tab not use d-none for hidding content in first place
import {setQueryParam, getQueryParam} from "/system.js";
import {setParamOnUrls} from "/modifyurls.js";

//
export function activateTabOn(tabsId, tab, apiName, sourceName) {
   return e => {
      // console.log(tabsId, tab, apiName, sourceName, e.detail)
      if ([e.detail.targetName, e.detail.apiName].includes(apiName)) {
         if (!sourceName || sourceName == e.detail.srcElement?.getAttribute('data-loader')) {
            activateTab({tabId: tab}, tabsId)
         }
      }
   }
}

export function activateTab(tab, tabsId) {
   const tabsmain = document.getElementById(tabsId)
   // console.log('activatetab',tab,tabsId)
   if (tabsmain && tab?.tabId) {
      const tabbtn = tabsmain.querySelector('[data-click*="tabs|' + tab.tabId + '"]')
      if (tabbtn) {
         const commands = tabbtn.getAttribute('data-click')
         if (commands) {
            const commandlist = commands.split(' ')
            for (let a of commandlist) {
               const params = a.split('|')
               const name = params.shift()
               showTab(params, tabsmain)
            }
         }
         activateTabBtn(tabsmain, tabbtn)
      } else if (tab.tabId) console.error('restoreTab missing tab', tab.tabsId, tab.tabId)
   }
}

export function restoreTab(tabsId) {
   if (tabsId) {
      const tab = getTabsFromUrl(tabsId).shift()
      activateTab(tab, tabsId)
   }
}

export function tabs(e, ...targets) {
   let el = e.target
   let parent = el.parentElement
   while (!parent.id) parent = parent.parentElement
   activateTabBtn(parent, el, e.target.nodeName)
   showTab(targets, parent)
}

function showTab(targets, parent) {
   const hideclass = parent.getAttribute('data-tab-class') || 'g-dh'
   const tabsID = parent.id
   // console.log('tablisttools', targets, tabsID)
   targets.forEach(id => {
      const tab = document.getElementById(id)
      if (tab) {
         if (targets[0] === id) {
            tab.classList.remove(hideclass)
            saveTabToUrl(tabsID, id)
         } else tab.classList.add(hideclass)
      }
   })
}

function saveTabToUrl(tabsID, id) {
   const tabs = getTabsFromUrl()
   let value = ''
   if (tabs.length) {
      tabs.forEach(tab => {
         if (tab.tabsId !== tabsID) {
            value += ' ' + tab.tabsId + '|' + tab.tabId
         }
      })
   }
   value += ' ' + tabsID + '|' + id

   setQueryParam('tabs', value.trim())
   setParamOnUrls('tabs', value.trim())
}

function getTabsFromUrl(id) {
   const ovalue = getQueryParam('tabs') || ''
   const out = []
   ovalue.split(' ').forEach(tab => {
      const [tabsId, tabId] = tab.split('|')
      const a = {tabsId: tabsId, tabId: tabId}
      if (id) {
         if (a.tabsId === id)
            out.push(a)
      } else if (a.tabsId && a.tabId) out.push(a)
      // console.log('tablisttools', out)
   })
   return out
}

function activateTabBtn(tabEl, btnEl, btnTagname) {
   if (!btnTagname) btnTagname = 'button'
   tabEl.querySelectorAll(btnTagname + '[data-active-classes]').forEach(btn => {
      const classes = btn.getAttribute('data-active-classes')
      if (btn === btnEl) {
         classes.split(' ').forEach(c => {
            btn.classList.add(c)
         })
      } else {
         classes.split(' ').forEach(c => {
            btn.classList.remove(c)
         })
      }
   })
}
