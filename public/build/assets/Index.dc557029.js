import{_ as D}from"./Authenticated.3a544bed.js";import{_ as u}from"./Button.be945d63.js";import I from"./Form.a1b48d95.js";import{_ as O}from"./Paginator.d94b1d1a.js";import{_ as R,r as A}from"./SearchInput.af49f9a9.js";import{_ as E}from"./MultiSelect.fb25c41f.js";import{_ as P,a as b}from"./TableData.e7cfe68e.js";import{_ as T}from"./TableHeadSort.5d066413.js";import{g as m,h as U,f as x,a as t,u as i,w as o,F as B,o as d,Z,b as e,d as f,t as g,k as q,l as w,c as S,O as k}from"./app.242e5fba.js";import{r as z}from"./PlusIcon.106d5b37.js";import{r as G}from"./BackspaceIcon.dce1ca77.js";import{r as H}from"./PencilSquareIcon.47aec97a.js";import{r as J}from"./TrashIcon.4a89c528.js";import"./keyboard.034c1cc1.js";import"./use-resolve-button-type.ce060f77.js";import"./RectangleStackIcon.e3df9db3.js";import"./FormInput.15b0f65d.js";import"./Modal.c8b43892.js";import"./disposables.a3cad5e2.js";import"./ArrowUturnLeftIcon.88830442.js";import"./CheckCircleIcon.a28321ba.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.1155e6b8.js";const Q=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Roles) ",-1),W={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},X={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Y={class:"flex justify-end"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},oe={class:"mt-3"},le={class:"flex space-x-1"},ae=e("span",null," Search ",-1),ne=e("span",null," Reset ",-1),re={class:"flex flex-col space-y-2"},ie={class:"text-sm text-gray-700 leading-5 flex space-x-1"},de=e("span",null,"Showing",-1),ce={class:"font-medium"},ue=e("span",null,"to",-1),me={class:"font-medium"},fe=e("span",null,"of",-1),ge={class:"font-medium"},pe=e("span",null,"results",-1),xe={class:"mt-6 flex flex-col"},_e={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},he={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ve={class:"min-w-full border-separate",style:{"border-spacing":"0"}},ye={class:"bg-gray-100"},be={class:"divide-x divide-gray-200"},we={class:"bg-white"},ke={class:"flex justify-center space-x-1"},Ce=e("span",null," Edit ",-1),$e=e("span",null," Delete ",-1),Pe={key:0},Be=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Se=[Be],We={__name:"Index",props:{roles:Object},setup(l){const a=m({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),c=m(!1),_=m(),h=m(""),v=m([]);U(()=>{v.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],a.value.numberPerPage=v.value[0]});function V(){h.value="create",_.value=null,c.value=!0}function N(r){!confirm("Are you sure to delete "+r.name+"?")||k.delete("/roles/"+r.id)}function L(r){h.value="update",_.value=r,c.value=!0}function y(){k.get("/roles",{...a.value,numberPerPage:a.value.numberPerPage.id},{preserveState:!0,replace:!0})}function M(){k.get("/roles")}function F(r){a.value.sortKey=r,a.value.sortBy=!a.value.sortBy,y()}function K(){c.value=!1}return(r,s)=>(d(),x(B,null,[t(i(Z),{title:"Roles"}),t(D,null,{header:o(()=>[Q]),default:o(()=>{var C,$;return[e("div",W,[e("div",X,[e("div",Y,[t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[0]||(s[0]=n=>V())},{default:o(()=>[t(i(z),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(R,{placeholderStr:"Name",modelValue:a.value.name,"onUpdate:modelValue":s[1]||(s[1]=n=>a.value.name=n)},{default:o(()=>[f(" Name ")]),_:1},8,["modelValue"])]),e("div",se,[e("div",oe,[e("div",le,[t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[2]||(s[2]=n=>y())},{default:o(()=>[t(i(A),{class:"h-4 w-4","aria-hidden":"true"}),ae]),_:1}),t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[3]||(s[3]=n=>M())},{default:o(()=>[t(i(G),{class:"h-4 w-4","aria-hidden":"true"}),ne]),_:1})])]),e("div",re,[e("p",ie,[de,e("span",ce,g((C=l.roles.meta.from)!=null?C:0),1),ue,e("span",me,g(($=l.roles.meta.to)!=null?$:0),1),fe,e("span",ge,g(l.roles.meta.total),1),pe]),t(E,{modelValue:a.value.numberPerPage,"onUpdate:modelValue":s[4]||(s[4]=n=>a.value.numberPerPage=n),options:v.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:y},null,8,["modelValue","options"])])])]),e("div",xe,[e("div",_e,[e("div",he,[e("table",ve,[e("thead",ye,[e("tr",be,[t(P,null,{default:o(()=>[f(" # ")]),_:1}),t(T,{modelName:"name",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:s[5]||(s[5]=n=>F("name"))},{default:o(()=>[f(" Name ")]),_:1},8,["sortKey","sortBy"]),t(P)])]),e("tbody",we,[(d(!0),x(B,null,q(l.roles.data,(n,p)=>(d(),x("tr",{key:n.id,class:"divide-x divide-gray-200"},[t(b,{currentIndex:p,totalLength:l.roles.length,inputClass:"text-center"},{default:o(()=>[f(g(l.roles.meta.from+p),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:p,totalLength:l.roles.length,inputClass:"text-left"},{default:o(()=>[f(g(n.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:p,totalLength:l.roles.length,inputClass:"text-center"},{default:o(()=>[e("div",ke,[t(u,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:j=>L(n)},{default:o(()=>[t(i(H),{class:"w-4 h-4"}),Ce]),_:2},1032,["onClick"]),t(u,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:j=>N(n)},{default:o(()=>[t(i(J),{class:"w-4 h-4"}),$e]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),l.roles.data.length?w("",!0):(d(),x("tr",Pe,Se))])]),l.roles.data.length?(d(),S(O,{key:0,links:l.roles.links,meta:l.roles.meta},null,8,["links","meta"])):w("",!0)])])])]),c.value?(d(),S(I,{key:0,role:_.value,type:h.value,showModal:c.value,onModalClose:K},null,8,["role","type","showModal"])):w("",!0)]}),_:1})],64))}};export{We as default};
