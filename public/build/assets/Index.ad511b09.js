import{_ as K}from"./Authenticated.7db80fdf.js";import{_ as y}from"./Button.23a05acd.js";import M from"./Form.ef6637ef.js";import{_ as A}from"./Paginator.7c2c62b7.js";import{_ as F,r as D}from"./SearchInput.bc3567b9.js";import{_ as E}from"./MultiSelect.2415f05c.js";import{_ as x,a as p}from"./TableData.b7faba11.js";import{_ as R}from"./TableHeadSort.124fba78.js";import{g as h,K as L,h as U,f as v,a as t,u as l,w as n,F as O,o as u,Z,b as e,c as b,l as _,d as i,t as c,k as q,O as w}from"./app.8d489fd7.js";import{r as H}from"./PlusIcon.77a7c9ec.js";import{r as J}from"./BackspaceIcon.2558c7fa.js";import{r as Q}from"./PencilSquareIcon.5e700db8.js";import{r as W}from"./TrashIcon.d725ce6e.js";import"./open-closed.13f31f1e.js";import"./use-resolve-button-type.add6567f.js";import"./RectangleStackIcon.71077489.js";import"./FormInput.b01b0517.js";import"./FormTextarea.e6fb38fd.js";import"./Modal.a8b67aa4.js";import"./CheckCircleIcon.7b2c8429.js";import"./PlusCircleIcon.829bff36.js";import"./ArrowUturnLeftIcon.6dcf8b2c.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.a2e98afe.js";const X=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Operators ",-1),Y={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},ee={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},te={class:"flex justify-end"},se=e("span",null," Create ",-1),ne={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},oe={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ae={class:"mt-3"},re={class:"flex space-x-1"},le=e("span",null," Search ",-1),ie=e("span",null," Reset ",-1),de={class:"flex flex-col space-y-2"},ue={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ce=e("span",null,"Showing",-1),me={class:"font-medium"},pe=e("span",null,"to",-1),fe={class:"font-medium"},ge=e("span",null,"of",-1),ye={class:"font-medium"},xe=e("span",null,"results",-1),he={class:"mt-6 flex flex-col"},_e={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ve={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},be={class:"min-w-full border-separate",style:{"border-spacing":"0"}},we={class:"bg-gray-100"},ke={class:"divide-x divide-gray-200"},Ce={class:"bg-white"},Pe={class:"flex justify-center space-x-1"},$e=e("span",null," Edit ",-1),Le=e("span",null," Delete ",-1),Ve={key:0},Be=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Oe=[Be],et={__name:"Index",props:{countries:Object,operators:Object,timezones:[Array,Object],countryPaymentGateways:Object,operatorPaymentGatewayTypes:[Array,Object],unbindedVends:Object},setup(s){const r=h({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),f=h(!1),k=h(),C=h(""),P=h([]);L().props.auth.operatorRole,L().props.auth.roles;const g=L().props.auth.permissions;U(()=>{P.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],r.value.numberPerPage=P.value[0]});function S(){C.value="create",k.value=null,f.value=!0}function j(d){!confirm("Are you sure to delete "+d.name+"?")||w.delete("/operators/"+d.id)}function G(d){C.value="update",k.value=d,w.visit(route("operators",{operator_id:d.id,country_id:d.country.id}),{only:["countryPaymentGateways","unbindedVends"],preserveState:!0}),f.value=!0}function $(){w.get("/operators",{...r.value,numberPerPage:r.value.numberPerPage.id},{preserveState:!0,replace:!0})}function N(){w.get("/operators")}function T(d){r.value.sortKey=d,r.value.sortBy=!r.value.sortBy,$()}function I(){f.value=!1}return(d,a)=>(u(),v(O,null,[t(l(Z),{title:"Operators"}),t(K,null,{header:n(()=>[X]),default:n(()=>{var V,B;return[e("div",Y,[e("div",ee,[e("div",te,[l(g).includes("create operators")&&l(g).includes("admin-access operators")?(u(),b(y,{key:0,class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[0]||(a[0]=o=>S())},{default:n(()=>[t(l(H),{class:"h-4 w-4","aria-hidden":"true"}),se]),_:1})):_("",!0)]),e("div",ne,[t(F,{placeholderStr:"Name",modelValue:r.value.name,"onUpdate:modelValue":a[1]||(a[1]=o=>r.value.name=o)},{default:n(()=>[i(" Name ")]),_:1},8,["modelValue"])]),e("div",oe,[e("div",ae,[e("div",re,[t(y,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[2]||(a[2]=o=>$())},{default:n(()=>[t(l(D),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1}),t(y,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[3]||(a[3]=o=>N())},{default:n(()=>[t(l(J),{class:"h-4 w-4","aria-hidden":"true"}),ie]),_:1})])]),e("div",de,[e("p",ue,[ce,e("span",me,c((V=s.operators.meta.from)!=null?V:0),1),pe,e("span",fe,c((B=s.operators.meta.to)!=null?B:0),1),ge,e("span",ye,c(s.operators.meta.total),1),xe]),t(E,{modelValue:r.value.numberPerPage,"onUpdate:modelValue":a[4]||(a[4]=o=>r.value.numberPerPage=o),options:P.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:$},null,8,["modelValue","options"])])])]),e("div",he,[e("div",_e,[e("div",ve,[e("table",be,[e("thead",we,[e("tr",ke,[t(x,null,{default:n(()=>[i(" # ")]),_:1}),t(x,null,{default:n(()=>[i(" Code ")]),_:1}),t(R,{modelName:"name",sortKey:r.value.sortKey,sortBy:r.value.sortBy,onSortTable:a[5]||(a[5]=o=>T("name"))},{default:n(()=>[i(" Name ")]),_:1},8,["sortKey","sortBy"]),t(x,null,{default:n(()=>[i(" Country ")]),_:1}),t(x,null,{default:n(()=>[i(" Timezone ")]),_:1}),t(x)])]),e("tbody",Ce,[(u(!0),v(O,null,q(s.operators.data,(o,m)=>(u(),v("tr",{key:o.id,class:"divide-x divide-gray-200"},[t(p,{currentIndex:m,totalLength:s.operators.length,inputClass:"text-center"},{default:n(()=>[i(c(s.operators.meta.from+m),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:m,totalLength:s.operators.length,inputClass:"text-center"},{default:n(()=>[i(c(o.code),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:m,totalLength:s.operators.length,inputClass:"text-left"},{default:n(()=>[i(c(o.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:m,totalLength:s.operators.length,inputClass:"text-center"},{default:n(()=>[i(c(o.country.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:m,totalLength:s.operators.length,inputClass:"text-center"},{default:n(()=>[i(c(o.timezone.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:m,totalLength:s.operators.length,inputClass:"text-center"},{default:n(()=>[e("div",Pe,[t(y,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:z=>G(o)},{default:n(()=>[t(l(Q),{class:"w-4 h-4"}),$e]),_:2},1032,["onClick"]),l(g).includes("delete operators")&&l(g).includes("admin-access operators")?(u(),b(y,{key:0,type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:z=>j(o)},{default:n(()=>[t(l(W),{class:"w-4 h-4"}),Le]),_:2},1032,["onClick"])):_("",!0)])]),_:2},1032,["currentIndex","totalLength"])]))),128)),s.operators.data.length?_("",!0):(u(),v("tr",Ve,Oe))])]),s.operators.data.length?(u(),b(A,{key:0,links:s.operators.links,meta:s.operators.meta},null,8,["links","meta"])):_("",!0)])])])]),f.value?(u(),b(M,{key:0,countries:s.countries,operator:k.value,timezones:s.timezones,type:C.value,showModal:f.value,countryPaymentGateways:s.countryPaymentGateways,operatorPaymentGatewayTypes:s.operatorPaymentGatewayTypes,permissions:l(g),unbindedVends:s.unbindedVends,onModalClose:I},null,8,["countries","operator","timezones","type","showModal","countryPaymentGateways","operatorPaymentGatewayTypes","permissions","unbindedVends"])):_("",!0)]}),_:1})],64))}};export{et as default};
