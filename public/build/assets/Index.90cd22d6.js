import{_ as j}from"./Authenticated.fb2385b7.js";import{_ as c}from"./Button.37f68050.js";import D from"./Form.ac0f6a87.js";import{_ as I}from"./Paginator.e935ece5.js";import{_ as O,r as A}from"./SearchInput.284e54ad.js";import{_ as E}from"./MultiSelect.418dfc00.js";import{_ as P,a as b}from"./TableData.ad1262ee.js";import{_ as U}from"./TableHeadSort.957a93c4.js";import{g as u,h as R,f as x,a as t,u as i,w as a,F as T,o as d,Z,b as e,d as f,t as p,k as q,l as w,c as B,O as k}from"./app.009697ae.js";import{r as z}from"./PlusIcon.58a26d2c.js";import{r as G}from"./BackspaceIcon.65987218.js";import{r as H}from"./PencilSquareIcon.8cc79a9c.js";import{r as J}from"./TrashIcon.0ccbfc48.js";import"./open-closed.0a17ce87.js";import"./use-resolve-button-type.af25536c.js";import"./RectangleStackIcon.00e2746e.js";import"./FormInput.262e39f7.js";import"./Modal.6b7b8f6f.js";import"./disposables.a63b89fa.js";import"./ArrowUturnLeftIcon.274a55d8.js";import"./CheckCircleIcon.dcf41f14.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.dbddf1f4.js";const Q=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Payment Terms) ",-1),W={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},X={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Y={class:"flex justify-end"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ae={class:"mt-3"},ne={class:"flex space-x-1"},oe=e("span",null," Search ",-1),le=e("span",null," Reset ",-1),re={class:"flex flex-col space-y-2"},ie={class:"text-sm text-gray-700 leading-5 flex space-x-1"},de=e("span",null,"Showing",-1),me={class:"font-medium"},ce=e("span",null,"to",-1),ue={class:"font-medium"},fe=e("span",null,"of",-1),pe={class:"font-medium"},ge=e("span",null,"results",-1),xe={class:"mt-6 flex flex-col"},_e={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},he={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ye={class:"min-w-full border-separate",style:{"border-spacing":"0"}},ve={class:"bg-gray-100"},be={class:"divide-x divide-gray-200"},we={class:"bg-white"},ke={class:"flex justify-center space-x-1"},Ce=e("span",null," Edit ",-1),$e=e("span",null," Delete ",-1),Pe={key:0},Te=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Be=[Te],We={__name:"Index",props:{paymentTerms:Object},setup(n){const o=u({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),m=u(!1),_=u(),h=u(""),y=u([]);R(()=>{y.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.numberPerPage=y.value[0]});function S(){h.value="create",_.value=null,m.value=!0}function V(r){!confirm("Are you sure to delete "+r.name+"?")||k.delete("/payment-terms/"+r.id)}function N(r){h.value="update",_.value=r,m.value=!0}function v(){k.get("/payment-terms",{...o.value,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0})}function L(){k.get("/payment-terms")}function M(r){o.value.sortKey=r,o.value.sortBy=!o.value.sortBy,v()}function F(){m.value=!1}return(r,s)=>(d(),x(T,null,[t(i(Z),{title:"Payment Terms"}),t(j,null,{header:a(()=>[Q]),default:a(()=>{var C,$;return[e("div",W,[e("div",X,[e("div",Y,[t(c,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[0]||(s[0]=l=>S())},{default:a(()=>[t(i(z),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(O,{placeholderStr:"Name",modelValue:o.value.name,"onUpdate:modelValue":s[1]||(s[1]=l=>o.value.name=l)},{default:a(()=>[f(" Name ")]),_:1},8,["modelValue"])]),e("div",se,[e("div",ae,[e("div",ne,[t(c,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[2]||(s[2]=l=>v())},{default:a(()=>[t(i(A),{class:"h-4 w-4","aria-hidden":"true"}),oe]),_:1}),t(c,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[3]||(s[3]=l=>L())},{default:a(()=>[t(i(G),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1})])]),e("div",re,[e("p",ie,[de,e("span",me,p((C=n.paymentTerms.meta.from)!=null?C:0),1),ce,e("span",ue,p(($=n.paymentTerms.meta.to)!=null?$:0),1),fe,e("span",pe,p(n.paymentTerms.meta.total),1),ge]),t(E,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":s[4]||(s[4]=l=>o.value.numberPerPage=l),options:y.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:v},null,8,["modelValue","options"])])])]),e("div",xe,[e("div",_e,[e("div",he,[e("table",ye,[e("thead",ve,[e("tr",be,[t(P,null,{default:a(()=>[f(" # ")]),_:1}),t(U,{modelName:"name",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[5]||(s[5]=l=>M("name"))},{default:a(()=>[f(" Name ")]),_:1},8,["sortKey","sortBy"]),t(P)])]),e("tbody",we,[(d(!0),x(T,null,q(n.paymentTerms.data,(l,g)=>(d(),x("tr",{key:l.id,class:"divide-x divide-gray-200"},[t(b,{currentIndex:g,totalLength:n.paymentTerms.length,inputClass:"text-center"},{default:a(()=>[f(p(n.paymentTerms.meta.from+g),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:g,totalLength:n.paymentTerms.length,inputClass:"text-left"},{default:a(()=>[f(p(l.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:g,totalLength:n.paymentTerms.length,inputClass:"text-center"},{default:a(()=>[e("div",ke,[t(c,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:K=>N(l)},{default:a(()=>[t(i(H),{class:"w-4 h-4"}),Ce]),_:2},1032,["onClick"]),t(c,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:K=>V(l)},{default:a(()=>[t(i(J),{class:"w-4 h-4"}),$e]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),n.paymentTerms.data.length?w("",!0):(d(),x("tr",Pe,Be))])]),n.paymentTerms.data.length?(d(),B(I,{key:0,links:n.paymentTerms.links,meta:n.paymentTerms.meta},null,8,["links","meta"])):w("",!0)])])])]),m.value?(d(),B(D,{key:0,paymentTerm:_.value,type:h.value,showModal:m.value,onModalClose:F},null,8,["paymentTerm","type","showModal"])):w("",!0)]}),_:1})],64))}};export{We as default};
