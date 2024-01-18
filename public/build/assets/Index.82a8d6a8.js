import{_ as D}from"./Authenticated.2173e553.js";import{_ as c}from"./Button.cbc6c4f7.js";import I from"./Form.5d7578aa.js";import{_ as O}from"./Paginator.b8117d59.js";import{_ as A,r as E}from"./SearchInput.cc867f55.js";import{_ as U}from"./MultiSelect.00bb94fa.js";import{_ as P,a as b}from"./TableData.0a9ac72a.js";import{_ as R}from"./TableHeadSort.cbcdcb3b.js";import{g as u,h as Z,f as x,a as t,u as i,w as o,F as B,o as d,Z as q,b as e,d as f,t as p,k as z,l as w,c as S,O as k}from"./app.3bb011da.js";import{r as G}from"./PlusIcon.94e470cc.js";import{r as H}from"./BackspaceIcon.a92ace62.js";import{r as J}from"./PencilSquareIcon.5ca444cf.js";import{r as Q}from"./TrashIcon.741e8e17.js";import"./keyboard.7cd0a8cb.js";import"./use-resolve-button-type.cfa0d75b.js";import"./RectangleStackIcon.4d273d35.js";import"./FormInput.4c8a7fe8.js";import"./Modal.de9a4ce9.js";import"./disposables.fa6fd73f.js";import"./ArrowUturnLeftIcon.f558bedd.js";import"./CheckCircleIcon.de6f86a7.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.f7d93b02.js";const T=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Permissions) ",-1),W={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},X={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Y={class:"flex justify-end"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},oe={class:"mt-3"},ne={class:"flex space-x-1"},ae=e("span",null," Search ",-1),le=e("span",null," Reset ",-1),re={class:"flex flex-col space-y-2"},ie={class:"text-sm text-gray-700 leading-5 flex space-x-1"},de=e("span",null,"Showing",-1),me={class:"font-medium"},ce=e("span",null,"to",-1),ue={class:"font-medium"},fe=e("span",null,"of",-1),pe={class:"font-medium"},ge=e("span",null,"results",-1),xe={class:"mt-6 flex flex-col"},_e={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},he={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ve={class:"min-w-full border-separate",style:{"border-spacing":"0"}},ye={class:"bg-gray-100"},be={class:"divide-x divide-gray-200"},we={class:"bg-white"},ke={class:"flex justify-center space-x-1"},Ce=e("span",null," Edit ",-1),$e=e("span",null," Delete ",-1),Pe={key:0},Be=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Se=[Be],We={__name:"Index",props:{permissions:Object},setup(n){const a=u({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),m=u(!1),_=u(),h=u(""),v=u([]);Z(()=>{v.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],a.value.numberPerPage=v.value[0]});function V(){h.value="create",_.value=null,m.value=!0}function N(r){!confirm("Are you sure to delete "+r.name+"?")||k.delete("/permissions/"+r.id)}function L(r){h.value="update",_.value=r,m.value=!0}function y(){k.get("/permissions",{...a.value,numberPerPage:a.value.numberPerPage.id},{preserveState:!0,replace:!0})}function M(){k.get("/permissions")}function F(r){a.value.sortKey=r,a.value.sortBy=!a.value.sortBy,y()}function K(){m.value=!1}return(r,s)=>(d(),x(B,null,[t(i(q),{title:"Permissions"}),t(D,null,{header:o(()=>[T]),default:o(()=>{var C,$;return[e("div",W,[e("div",X,[e("div",Y,[t(c,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[0]||(s[0]=l=>V())},{default:o(()=>[t(i(G),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(A,{placeholderStr:"Name",modelValue:a.value.name,"onUpdate:modelValue":s[1]||(s[1]=l=>a.value.name=l)},{default:o(()=>[f(" Name ")]),_:1},8,["modelValue"])]),e("div",se,[e("div",oe,[e("div",ne,[t(c,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[2]||(s[2]=l=>y())},{default:o(()=>[t(i(E),{class:"h-4 w-4","aria-hidden":"true"}),ae]),_:1}),t(c,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[3]||(s[3]=l=>M())},{default:o(()=>[t(i(H),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1})])]),e("div",re,[e("p",ie,[de,e("span",me,p((C=n.permissions.meta.from)!=null?C:0),1),ce,e("span",ue,p(($=n.permissions.meta.to)!=null?$:0),1),fe,e("span",pe,p(n.permissions.meta.total),1),ge]),t(U,{modelValue:a.value.numberPerPage,"onUpdate:modelValue":s[4]||(s[4]=l=>a.value.numberPerPage=l),options:v.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:y},null,8,["modelValue","options"])])])]),e("div",xe,[e("div",_e,[e("div",he,[e("table",ve,[e("thead",ye,[e("tr",be,[t(P,null,{default:o(()=>[f(" # ")]),_:1}),t(R,{modelName:"name",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:s[5]||(s[5]=l=>F("name"))},{default:o(()=>[f(" Name ")]),_:1},8,["sortKey","sortBy"]),t(P)])]),e("tbody",we,[(d(!0),x(B,null,z(n.permissions.data,(l,g)=>(d(),x("tr",{key:l.id,class:"divide-x divide-gray-200"},[t(b,{currentIndex:g,totalLength:n.permissions.length,inputClass:"text-center"},{default:o(()=>[f(p(n.permissions.meta.from+g),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:g,totalLength:n.permissions.length,inputClass:"text-left"},{default:o(()=>[f(p(l.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:g,totalLength:n.permissions.length,inputClass:"text-center"},{default:o(()=>[e("div",ke,[t(c,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:j=>L(l)},{default:o(()=>[t(i(J),{class:"w-4 h-4"}),Ce]),_:2},1032,["onClick"]),t(c,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:j=>N(l)},{default:o(()=>[t(i(Q),{class:"w-4 h-4"}),$e]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),n.permissions.data.length?w("",!0):(d(),x("tr",Pe,Se))])]),n.permissions.data.length?(d(),S(O,{key:0,links:n.permissions.links,meta:n.permissions.meta},null,8,["links","meta"])):w("",!0)])])])]),m.value?(d(),S(I,{key:0,permission:_.value,type:h.value,showModal:m.value,onModalClose:K},null,8,["permission","type","showModal"])):w("",!0)]}),_:1})],64))}};export{We as default};