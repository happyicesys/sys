import{_ as F}from"./Authenticated.3fdb5b43.js";import{_ as c}from"./Button.93b9e355.js";import K from"./Form.9a111825.js";import{r as D,a as A,T as P,_ as E,b as H,c as b}from"./TableHeadSort.caa09d33.js";import{_ as U}from"./SearchInput.59016853.js";import{_ as O}from"./MultiSelect.22b0e241.js";import{i as u,j as R,o as i,g as _,a as t,b as d,w as a,F as T,H as J,d as e,t as f,m as q,p as w,c as B,f as p,J as k}from"./app.cccf2071.js";import{r as z,a as G}from"./PlusIcon.2ee1838d.js";import{r as Q}from"./TrashIcon.888b95fa.js";import"./open-closed.95a674ff.js";import"./use-resolve-button-type.50a15f65.js";import"./RectangleStackIcon.51bfdc71.js";import"./FormInput.ef224c93.js";import"./Modal.f580d859.js";import"./ArrowUturnLeftIcon.c11e9d60.js";import"./CheckCircleIcon.31aa07aa.js";import"./_plugin-vue_export-helper.cdc0426e.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.2b2014c2.js";const W=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Payment Terms) ",-1),X={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Y={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Z={class:"flex justify-end"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se=p(" Name "),ae={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ne={class:"mt-3"},oe={class:"flex space-x-1"},le=e("span",null," Search ",-1),re=e("span",null," Reset ",-1),ie={class:"flex flex-col space-y-2"},de={class:"text-sm text-gray-700 leading-5 flex space-x-1"},me=e("span",null,"Showing",-1),ce={class:"font-medium"},ue=e("span",null,"to",-1),fe={class:"font-medium"},pe=e("span",null,"of",-1),ge={class:"font-medium"},_e=e("span",null,"results",-1),xe={class:"mt-6 flex flex-col"},he={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ye={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ve={class:"min-w-full border-separate",style:{"border-spacing":"0"}},be={class:"bg-gray-100"},we={class:"divide-x divide-gray-200"},ke=p(" # "),Ce=p(" Name "),$e={class:"bg-white"},Pe={class:"flex justify-center space-x-1"},Te=e("span",null," Edit ",-1),Be=e("span",null," Delete ",-1),Se={key:0},Ve=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ne=[Ve],We={__name:"Index",props:{paymentTerms:Object},setup(n){const o=u({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),m=u(!1),x=u(),h=u(""),y=u([]);R(()=>{y.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.numberPerPage=y.value[0]});function S(){h.value="create",x.value=null,m.value=!0}function V(r){!confirm("Are you sure to delete "+r.name+"?")||k.Inertia.delete("/payment-terms/"+r.id)}function N(r){h.value="update",x.value=r,m.value=!0}function v(){k.Inertia.get("/payment-terms",{...o.value,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0})}function I(){k.Inertia.get("/payment-terms")}function L(r){o.value.sortKey=r,o.value.sortBy=!o.value.sortBy,v()}function M(){m.value=!1}return(r,s)=>(i(),_(T,null,[t(d(J),{title:"Payment Terms"}),t(F,null,{header:a(()=>[W]),default:a(()=>{var C,$;return[e("div",X,[e("div",Y,[e("div",Z,[t(c,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[0]||(s[0]=l=>S())},{default:a(()=>[t(d(z),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(U,{placeholderStr:"Name",modelValue:o.value.name,"onUpdate:modelValue":s[1]||(s[1]=l=>o.value.name=l)},{default:a(()=>[se]),_:1},8,["modelValue"])]),e("div",ae,[e("div",ne,[e("div",oe,[t(c,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[2]||(s[2]=l=>v())},{default:a(()=>[t(d(D),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1}),t(c,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[3]||(s[3]=l=>I())},{default:a(()=>[t(d(A),{class:"h-4 w-4","aria-hidden":"true"}),re]),_:1})])]),e("div",ie,[e("p",de,[me,e("span",ce,f((C=n.paymentTerms.meta.from)!=null?C:0),1),ue,e("span",fe,f(($=n.paymentTerms.meta.to)!=null?$:0),1),pe,e("span",ge,f(n.paymentTerms.meta.total),1),_e]),t(O,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":s[4]||(s[4]=l=>o.value.numberPerPage=l),options:y.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:v},null,8,["modelValue","options"])])])]),e("div",xe,[e("div",he,[e("div",ye,[e("table",ve,[e("thead",be,[e("tr",we,[t(P,null,{default:a(()=>[ke]),_:1}),t(E,{modelName:"name",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[5]||(s[5]=l=>L("name"))},{default:a(()=>[Ce]),_:1},8,["sortKey","sortBy"]),t(P)])]),e("tbody",$e,[(i(!0),_(T,null,q(n.paymentTerms.data,(l,g)=>(i(),_("tr",{key:l.id,class:"divide-x divide-gray-200"},[t(b,{currentIndex:g,totalLength:n.paymentTerms.length,inputClass:"text-center"},{default:a(()=>[p(f(n.paymentTerms.meta.from+g),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:g,totalLength:n.paymentTerms.length,inputClass:"text-left"},{default:a(()=>[p(f(l.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:g,totalLength:n.paymentTerms.length,inputClass:"text-center"},{default:a(()=>[e("div",Pe,[t(c,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:j=>N(l)},{default:a(()=>[t(d(G),{class:"w-4 h-4"}),Te]),_:2},1032,["onClick"]),t(c,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:j=>V(l)},{default:a(()=>[t(d(Q),{class:"w-4 h-4"}),Be]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),n.paymentTerms.data.length?w("",!0):(i(),_("tr",Se,Ne))])]),n.paymentTerms.data.length?(i(),B(H,{key:0,links:n.paymentTerms.links,meta:n.paymentTerms.meta},null,8,["links","meta"])):w("",!0)])])])]),m.value?(i(),B(K,{key:0,paymentTerm:x.value,type:h.value,showModal:m.value,onModalClose:M},null,8,["paymentTerm","type","showModal"])):w("",!0)]}),_:1})],64))}};export{We as default};
