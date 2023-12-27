import{_ as D}from"./Authenticated.331d7020.js";import{_ as u}from"./Button.85606e25.js";import I from"./Form.9f70e7c3.js";import{_ as O}from"./Paginator.adaeaec3.js";import{_ as A,r as E}from"./SearchInput.4a4fc967.js";import{_ as T}from"./MultiSelect.3746a606.js";import{_ as P}from"./TableHead.44a25040.js";import{_ as y}from"./TableData.05031882.js";import{_ as U}from"./TableHeadSort.03b6de6c.js";import{g as m,h as R,f as x,a as t,u as i,w as a,F as B,o as d,Z,b as e,d as f,t as p,k as q,l as k,c as S,O as w}from"./app.f2ff53a8.js";import{r as z}from"./PlusIcon.08ae90b2.js";import{r as G}from"./BackspaceIcon.1aa9c77c.js";import{r as H}from"./PencilSquareIcon.971f8184.js";import{r as J}from"./TrashIcon.a5036661.js";import"./open-closed.6663d6b9.js";import"./use-resolve-button-type.04333418.js";import"./RectangleStackIcon.4976b5b8.js";import"./FormInput.36df637c.js";import"./Modal.ca0606ec.js";import"./disposables.aa6afe04.js";import"./ArrowUturnLeftIcon.f5fb167e.js";import"./CheckCircleIcon.c32a27c7.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.176dfd01.js";const Q=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Setting (Banks) ",-1),W={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},X={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Y={class:"flex justify-end"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ae={class:"mt-3"},ne={class:"flex space-x-1"},oe=e("span",null," Search ",-1),le=e("span",null," Reset ",-1),re={class:"flex flex-col space-y-2"},ie={class:"text-sm text-gray-700 leading-5 flex space-x-1"},de=e("span",null,"Showing",-1),ce={class:"font-medium"},ue=e("span",null,"to",-1),me={class:"font-medium"},fe=e("span",null,"of",-1),pe={class:"font-medium"},ge=e("span",null,"results",-1),xe={class:"mt-6 flex flex-col"},_e={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},he={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ve={class:"min-w-full border-separate",style:{"border-spacing":"0"}},be={class:"bg-gray-100"},ye={class:"divide-x divide-gray-200"},ke={class:"bg-white"},we={class:"flex justify-center space-x-1"},Ce=e("span",null," Edit ",-1),$e=e("span",null," Delete ",-1),Pe={key:0},Be=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Se=[Be],Xe={__name:"Index",props:{banks:Object},setup(n){const o=m({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),c=m(!1),_=m(),h=m(""),v=m([]);R(()=>{v.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.numberPerPage=v.value[0]});function V(){h.value="create",_.value=null,c.value=!0}function N(r){!confirm("Are you sure to delete "+r.name+"?")||w.delete("/banks/"+r.id)}function L(r){h.value="update",_.value=r,c.value=!0}function b(){w.get("/banks",{...o.value,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0})}function M(){w.get("/banks")}function F(r){o.value.sortKey=r,o.value.sortBy=!o.value.sortBy,b()}function K(){c.value=!1}return(r,s)=>(d(),x(B,null,[t(i(Z),{title:"Banks"}),t(D,null,{header:a(()=>[Q]),default:a(()=>{var C,$;return[e("div",W,[e("div",X,[e("div",Y,[t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[0]||(s[0]=l=>V())},{default:a(()=>[t(i(z),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(A,{placeholderStr:"Name",modelValue:o.value.name,"onUpdate:modelValue":s[1]||(s[1]=l=>o.value.name=l)},{default:a(()=>[f(" Name ")]),_:1},8,["modelValue"])]),e("div",se,[e("div",ae,[e("div",ne,[t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[2]||(s[2]=l=>b())},{default:a(()=>[t(i(E),{class:"h-4 w-4","aria-hidden":"true"}),oe]),_:1}),t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[3]||(s[3]=l=>M())},{default:a(()=>[t(i(G),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1})])]),e("div",re,[e("p",ie,[de,e("span",ce,p((C=n.banks.meta.from)!=null?C:0),1),ue,e("span",me,p(($=n.banks.meta.to)!=null?$:0),1),fe,e("span",pe,p(n.banks.meta.total),1),ge]),t(T,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":s[4]||(s[4]=l=>o.value.numberPerPage=l),options:v.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:b},null,8,["modelValue","options"])])])]),e("div",xe,[e("div",_e,[e("div",he,[e("table",ve,[e("thead",be,[e("tr",ye,[t(P,null,{default:a(()=>[f(" # ")]),_:1}),t(U,{modelName:"name",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[5]||(s[5]=l=>F("name"))},{default:a(()=>[f(" Name ")]),_:1},8,["sortKey","sortBy"]),t(P)])]),e("tbody",ke,[(d(!0),x(B,null,q(n.banks.data,(l,g)=>(d(),x("tr",{key:l.id,class:"divide-x divide-gray-200"},[t(y,{currentIndex:g,totalLength:n.banks.length,inputClass:"text-center"},{default:a(()=>[f(p(n.banks.meta.from+g),1)]),_:2},1032,["currentIndex","totalLength"]),t(y,{currentIndex:g,totalLength:n.banks.length,inputClass:"text-left"},{default:a(()=>[f(p(l.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(y,{currentIndex:g,totalLength:n.banks.length,inputClass:"text-center"},{default:a(()=>[e("div",we,[t(u,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:j=>L(l)},{default:a(()=>[t(i(H),{class:"w-4 h-4"}),Ce]),_:2},1032,["onClick"]),t(u,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:j=>N(l)},{default:a(()=>[t(i(J),{class:"w-4 h-4"}),$e]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),n.banks.data.length?k("",!0):(d(),x("tr",Pe,Se))])]),n.banks.data.length?(d(),S(O,{key:0,links:n.banks.links,meta:n.banks.meta},null,8,["links","meta"])):k("",!0)])])])]),c.value?(d(),S(I,{key:0,bank:_.value,type:h.value,showModal:c.value,onModalClose:K},null,8,["bank","type","showModal"])):k("",!0)]}),_:1})],64))}};export{Xe as default};
