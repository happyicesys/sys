import{_ as K}from"./Authenticated.29c5076b.js";import{_ as v}from"./Button.4b8981fe.js";import O from"./Form.15d4a21e.js";import{_ as U}from"./Paginator.e614741d.js";import{_ as Q}from"./SearchInput.4a4ac3b9.js";import{_ as T}from"./MultiSelect.64d3ffc2.js";import{_ as c,a as d}from"./TableData.01fe7451.js";import{_ as A}from"./TableHeadSort.ae636578.js";import{g,j as D,h as Z,f as b,a as t,u as h,w as n,F as B,o as m,Z as q,b as e,d as l,t as i,k as z,l as C,c as $,Q as G,O as S}from"./app.c8734f48.js";import H from"./ExchangeRate.80cd300a.js";import{r as J}from"./PlusIcon.12e7dc3e.js";import{r as W}from"./MagnifyingGlassIcon.3ac4d8fb.js";import{r as X}from"./BackspaceIcon.f0bd4e7c.js";import{r as Y}from"./PlusCircleIcon.bad59bca.js";import{r as ee}from"./PencilSquareIcon.ae1c0ad1.js";import"./keyboard.d1255e04.js";import"./use-resolve-button-type.cb4535c6.js";import"./RectangleStackIcon.08fda477.js";import"./FormInput.06b6bdfe.js";import"./Modal.ab16ba7c.js";import"./disposables.1619ac17.js";import"./ArrowUturnLeftIcon.c6295de0.js";import"./CheckCircleIcon.d7bc9c9a.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.3b89a0cf.js";const te=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Country & Currency) ",-1),ne={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},se={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},ae={class:"flex justify-end"},le=e("span",null," Create ",-1),oe={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},re={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ie={class:"mt-3"},ue={class:"flex space-x-1"},de=e("span",null," Search ",-1),ce=e("span",null," Reset ",-1),me={class:"flex flex-col space-y-2"},fe={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ge=e("span",null,"Showing",-1),he={class:"font-medium"},xe=e("span",null,"to",-1),pe={class:"font-medium"},_e=e("span",null,"of",-1),ve={class:"font-medium"},ye=e("span",null,"results",-1),be={class:"mt-6 flex flex-col"},Ce={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},we={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ke={class:"min-w-full border-separate",style:{"border-spacing":"0"}},$e={class:"bg-gray-100"},Le={class:"divide-x divide-gray-200"},Pe=e("br",null,null,-1),Be={class:"bg-white"},Se={class:"flex justify-center space-x-1"},Me=e("span",null," Rate ",-1),Ve=e("span",null," Edit ",-1),Ie={key:0},Ne=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ee=[Ne],lt={__name:"Index",props:{countries:Object},setup(a){const r=g({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),x=g(!1),y=g(!1),p=g(),_=g(""),w=g([]),M=D(()=>G().props.auth.user);Z(()=>{w.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],r.value.numberPerPage=w.value[0]});function V(){_.value="create",p.value=null,x.value=!0}function I(f){_.value="update",p.value=f,x.value=!0}function N(f){_.value="update",p.value=f,y.value=!0}function k(){S.get("/countries",{...r.value,numberPerPage:r.value.numberPerPage.id},{preserveState:!0,replace:!0})}function E(){S.get("/countries")}function R(f){r.value.sortKey=f,r.value.sortBy=!r.value.sortBy,k()}function j(){x.value=!1}return(f,o)=>(m(),b(B,null,[t(h(q),{title:"Country"}),t(K,null,{header:n(()=>[te]),default:n(()=>{var L,P;return[e("div",ne,[e("div",se,[e("div",ae,[t(v,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[0]||(o[0]=s=>V())},{default:n(()=>[t(h(J),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1})]),e("div",oe,[t(Q,{placeholderStr:"Name",modelValue:r.value.name,"onUpdate:modelValue":o[1]||(o[1]=s=>r.value.name=s)},{default:n(()=>[l(" Name ")]),_:1},8,["modelValue"])]),e("div",re,[e("div",ie,[e("div",ue,[t(v,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[2]||(o[2]=s=>k())},{default:n(()=>[t(h(W),{class:"h-4 w-4","aria-hidden":"true"}),de]),_:1}),t(v,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[3]||(o[3]=s=>E())},{default:n(()=>[t(h(X),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1})])]),e("div",me,[e("p",fe,[ge,e("span",he,i((L=a.countries.meta.from)!=null?L:0),1),xe,e("span",pe,i((P=a.countries.meta.to)!=null?P:0),1),_e,e("span",ve,i(a.countries.meta.total),1),ye]),t(T,{modelValue:r.value.numberPerPage,"onUpdate:modelValue":o[4]||(o[4]=s=>r.value.numberPerPage=s),options:w.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:k},null,8,["modelValue","options"])])])]),e("div",be,[e("div",Ce,[e("div",we,[e("table",ke,[e("thead",$e,[e("tr",Le,[t(c,null,{default:n(()=>[l(" # ")]),_:1}),t(A,{modelName:"name",sortKey:r.value.sortKey,sortBy:r.value.sortBy,onSortTable:o[5]||(o[5]=s=>R("name"))},{default:n(()=>[l(" Name ")]),_:1},8,["sortKey","sortBy"]),t(c,null,{default:n(()=>[l(" Code ")]),_:1}),t(c,null,{default:n(()=>[l(" Currency ")]),_:1}),t(c,null,{default:n(()=>[l(" Symbol ")]),_:1}),t(c,null,{default:n(()=>[l(" Phone Code ")]),_:1}),t(c,null,{default:n(()=>[l(" Latest Rate "),Pe,l(" (Base: "+i(M.value.profile.base_currency.currency_name)+") ",1)]),_:1}),t(c)])]),e("tbody",Be,[(m(!0),b(B,null,z(a.countries.data,(s,u)=>(m(),b("tr",{key:s.id,class:"divide-x divide-gray-200"},[t(d,{currentIndex:u,totalLength:a.countries.length,inputClass:"text-center"},{default:n(()=>[l(i(a.countries.meta.from+u),1)]),_:2},1032,["currentIndex","totalLength"]),t(d,{currentIndex:u,totalLength:a.countries.length,inputClass:"text-center"},{default:n(()=>[l(i(s.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(d,{currentIndex:u,totalLength:a.countries.length,inputClass:"text-center"},{default:n(()=>[l(i(s.code),1)]),_:2},1032,["currentIndex","totalLength"]),t(d,{currentIndex:u,totalLength:a.countries.length,inputClass:"text-center"},{default:n(()=>[l(i(s.currency_name),1)]),_:2},1032,["currentIndex","totalLength"]),t(d,{currentIndex:u,totalLength:a.countries.length,inputClass:"text-center"},{default:n(()=>[l(i(s.currency_symbol),1)]),_:2},1032,["currentIndex","totalLength"]),t(d,{currentIndex:u,totalLength:a.countries.length,inputClass:"text-center"},{default:n(()=>[l(i(s.phone_code),1)]),_:2},1032,["currentIndex","totalLength"]),t(d,{currentIndex:u,totalLength:a.countries.length,inputClass:"text-right"},{default:n(()=>[l(i(s.latestQuoteExchangeRate?s.latestQuoteExchangeRate.rate:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(d,{currentIndex:u,totalLength:a.countries.length,inputClass:"text-center"},{default:n(()=>[e("div",Se,[t(v,{type:"button",class:"bg-green-300 hover:bg-green-400 px-3 py-2 text-xs text-green-800 flex space-x-1",onClick:F=>N(s)},{default:n(()=>[t(h(Y),{class:"h-4 w-4","aria-hidden":"true"}),Me]),_:2},1032,["onClick"]),t(v,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:F=>I(s)},{default:n(()=>[t(h(ee),{class:"w-4 h-4"}),Ve]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),a.countries.data.length?C("",!0):(m(),b("tr",Ie,Ee))])]),a.countries.data.length?(m(),$(U,{key:0,links:a.countries.links,meta:a.countries.meta},null,8,["links","meta"])):C("",!0)])])])]),x.value?(m(),$(O,{key:0,country:p.value,type:_.value,showModal:x.value,onModalClose:j},null,8,["country","type","showModal"])):C("",!0),y.value?(m(),$(H,{key:1,country:p.value,type:_.value,showModal:y.value,onModalClose:o[6]||(o[6]=s=>y.value=!1)},null,8,["country","type","showModal"])):C("",!0)]}),_:1})],64))}};export{lt as default};
