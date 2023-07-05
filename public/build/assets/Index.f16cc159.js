import{_ as M}from"./Authenticated.53ff1460.js";import{_ as x}from"./Button.d524e330.js";import A from"./Form.bb8729a5.js";import{_ as h,a as F,b as p}from"./TableData.2791c6df.js";import{_ as R,r as D}from"./SearchInput.a56d8ae7.js";import{_ as E}from"./MultiSelect.da29168f.js";import{_ as U}from"./TableHeadSort.4ee12344.js";import{h as v,K as L,j as Z,f as _,a as t,u as l,w as n,F as O,o as u,Z as q,b as e,c as b,m as f,d as i,t as c,l as H,O as w}from"./app.f0fe6523.js";import{r as J}from"./PlusIcon.91760aa7.js";import{r as Q}from"./BackspaceIcon.3cd735de.js";import{r as W}from"./PencilSquareIcon.e12378ba.js";import{r as X}from"./TrashIcon.19c3477d.js";import"./open-closed.5db5972d.js";import"./use-resolve-button-type.317be06f.js";import"./RectangleStackIcon.d53c262a.js";import"./FormInput.69b9d4cf.js";import"./FormTextarea.b6b97ba2.js";import"./Modal.07f3a6a7.js";import"./PlusCircleIcon.0d114f00.js";import"./ArrowUturnLeftIcon.ec5f4e70.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.4bf9836f.js";const Y=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Operators ",-1),ee={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},te={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},se={class:"flex justify-end"},ne=e("span",null," Create ",-1),oe={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ae={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},re={class:"mt-3"},le={class:"flex space-x-1"},ie=e("span",null," Search ",-1),de=e("span",null," Reset ",-1),ue={class:"flex flex-col space-y-2"},ce={class:"text-sm text-gray-700 leading-5 flex space-x-1"},me=e("span",null,"Showing",-1),pe={class:"font-medium"},fe=e("span",null,"to",-1),ge={class:"font-medium"},ye=e("span",null,"of",-1),xe={class:"font-medium"},he=e("span",null,"results",-1),ve={class:"mt-6 flex flex-col"},_e={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},be={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},we={class:"min-w-full border-separate",style:{"border-spacing":"0"}},ke={class:"bg-gray-100"},Ce={class:"divide-x divide-gray-200"},Pe={class:"bg-white"},$e={key:0,class:"flex justify-center space-x-1"},Le=e("span",null," Edit ",-1),Ve=e("span",null," Delete ",-1),Be={key:0},Oe=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),je=[Oe],Ye={__name:"Index",props:{countries:Object,operators:Object,timezones:[Array,Object],countryPaymentGateways:Object,operatorPaymentGatewayTypes:[Array,Object],unbindedVends:Object},setup(s){const r=v({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),g=v(!1),k=v(),C=v(""),P=v([]),j=L().props.auth.operatorRole;L().props.auth.roles;const y=L().props.auth.permissions;Z(()=>{P.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],r.value.numberPerPage=P.value[0]});function S(){C.value="create",k.value=null,g.value=!0}function G(d){!confirm("Are you sure to delete "+d.name+"?")||w.delete("/operators/"+d.id)}function N(d){C.value="update",k.value=d,w.visit(route("operators",{operator_id:d.id,country_id:d.country.id}),{only:["countryPaymentGateways","unbindedVends"],preserveState:!0}),g.value=!0}function $(){w.get("/operators",{...r.value,numberPerPage:r.value.numberPerPage.id},{preserveState:!0,replace:!0})}function T(){w.get("/operators")}function I(d){r.value.sortKey=d,r.value.sortBy=!r.value.sortBy,$()}function z(){g.value=!1}return(d,a)=>(u(),_(O,null,[t(l(q),{title:"Operators"}),t(M,null,{header:n(()=>[Y]),default:n(()=>{var V,B;return[e("div",ee,[e("div",te,[e("div",se,[l(y).includes("create operators")&&l(y).includes("admin-access operators")?(u(),b(x,{key:0,class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[0]||(a[0]=o=>S())},{default:n(()=>[t(l(J),{class:"h-4 w-4","aria-hidden":"true"}),ne]),_:1})):f("",!0)]),e("div",oe,[t(R,{placeholderStr:"Name",modelValue:r.value.name,"onUpdate:modelValue":a[1]||(a[1]=o=>r.value.name=o)},{default:n(()=>[i(" Name ")]),_:1},8,["modelValue"])]),e("div",ae,[e("div",re,[e("div",le,[t(x,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[2]||(a[2]=o=>$())},{default:n(()=>[t(l(D),{class:"h-4 w-4","aria-hidden":"true"}),ie]),_:1}),t(x,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[3]||(a[3]=o=>T())},{default:n(()=>[t(l(Q),{class:"h-4 w-4","aria-hidden":"true"}),de]),_:1})])]),e("div",ue,[e("p",ce,[me,e("span",pe,c((V=s.operators.meta.from)!=null?V:0),1),fe,e("span",ge,c((B=s.operators.meta.to)!=null?B:0),1),ye,e("span",xe,c(s.operators.meta.total),1),he]),t(E,{modelValue:r.value.numberPerPage,"onUpdate:modelValue":a[4]||(a[4]=o=>r.value.numberPerPage=o),options:P.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:$},null,8,["modelValue","options"])])])]),e("div",ve,[e("div",_e,[e("div",be,[e("table",we,[e("thead",ke,[e("tr",Ce,[t(h,null,{default:n(()=>[i(" # ")]),_:1}),t(h,null,{default:n(()=>[i(" Code ")]),_:1}),t(U,{modelName:"name",sortKey:r.value.sortKey,sortBy:r.value.sortBy,onSortTable:a[5]||(a[5]=o=>I("name"))},{default:n(()=>[i(" Name ")]),_:1},8,["sortKey","sortBy"]),t(h,null,{default:n(()=>[i(" Country ")]),_:1}),t(h,null,{default:n(()=>[i(" Timezone ")]),_:1}),t(h)])]),e("tbody",Pe,[(u(!0),_(O,null,H(s.operators.data,(o,m)=>(u(),_("tr",{key:o.id,class:"divide-x divide-gray-200"},[t(p,{currentIndex:m,totalLength:s.operators.length,inputClass:"text-center"},{default:n(()=>[i(c(s.operators.meta.from+m),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:m,totalLength:s.operators.length,inputClass:"text-center"},{default:n(()=>[i(c(o.code),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:m,totalLength:s.operators.length,inputClass:"text-left"},{default:n(()=>[i(c(o.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:m,totalLength:s.operators.length,inputClass:"text-center"},{default:n(()=>[i(c(o.country.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:m,totalLength:s.operators.length,inputClass:"text-center"},{default:n(()=>[i(c(o.timezone.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:m,totalLength:s.operators.length,inputClass:"text-center"},{default:n(()=>[l(j)?f("",!0):(u(),_("div",$e,[t(x,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:K=>N(o)},{default:n(()=>[t(l(W),{class:"w-4 h-4"}),Le]),_:2},1032,["onClick"]),l(y).includes("delete operators")&&l(y).includes("admin-access operators")?(u(),b(x,{key:0,type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:K=>G(o)},{default:n(()=>[t(l(X),{class:"w-4 h-4"}),Ve]),_:2},1032,["onClick"])):f("",!0)]))]),_:2},1032,["currentIndex","totalLength"])]))),128)),s.operators.data.length?f("",!0):(u(),_("tr",Be,je))])]),s.operators.data.length?(u(),b(F,{key:0,links:s.operators.links,meta:s.operators.meta},null,8,["links","meta"])):f("",!0)])])])]),g.value?(u(),b(A,{key:0,countries:s.countries,operator:k.value,timezones:s.timezones,type:C.value,showModal:g.value,countryPaymentGateways:s.countryPaymentGateways,operatorPaymentGatewayTypes:s.operatorPaymentGatewayTypes,permissions:l(y),unbindedVends:s.unbindedVends,onModalClose:z},null,8,["countries","operator","timezones","type","showModal","countryPaymentGateways","operatorPaymentGatewayTypes","permissions","unbindedVends"])):f("",!0)]}),_:1})],64))}};export{Ye as default};
