import{_ as F}from"./Authenticated.d2fcd47a.js";import{_ as U}from"./Button.dfd2f81b.js";import{r as H,a as A,T as p,_ as V,b as q,c as m}from"./TableHeadSort.5b58cc2b.js";import{_ as x}from"./SearchInput.a15963ed.js";import{_ as C}from"./MultiSelect.cf43cf99.js";import{i as y,j as M,o as g,g as _,a as n,b as N,w as l,F as v,H as z,d as t,t as r,m as B,p as G,c as R,f as a,J as P,n as L}from"./app.79e6231b.js";import"./open-closed.c98dbb30.js";import"./use-resolve-button-type.06f97aa1.js";import"./RectangleStackIcon.2eb18fef.js";import"./_plugin-vue_export-helper.cdc0426e.js";const J=t("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (List) ",-1),Q={class:"m-2 sm:mx-5 sm:my-3 px-2 sm:px-4 lg:px-6"},W={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-5 px-3 md:px-6 py-6"},X={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},Y=a(" Vend ID "),Z=a(" Serial Num "),ee=a(" Temp >> "),te=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Errors? ",-1),ne=a(" Cust ID "),oe=a(" Cust ID Name "),se=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),le=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),ae={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},re={class:"mt-3"},de={class:"flex space-x-1"},ie=t("span",null," Search ",-1),ue=t("span",null," Reset ",-1),ce={class:"flex flex-col space-y-2"},me={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ge=t("span",null,"Showing",-1),_e={class:"font-medium"},pe=t("span",null,"to",-1),fe={class:"font-medium"},he=t("span",null,"of",-1),xe={class:"font-medium"},ye=t("span",null,"results",-1),ve={class:"mt-6 flex flex-col"},be={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},Ve={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Ce={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Be={class:"bg-gray-100"},Le={class:"divide-x divide-gray-200"},ke=a(" # "),we=a(" Code "),Se=a(" Temp "),Ie=a(" Name "),Ne=a(" Category "),Pe=a(" Channel Status "),Te=a(" Inventory Status "),$e=a(" Last Temp "),Ke=a(" Coin Amount "),Ee=a(" Serial Num "),Oe=a(" Firmware Ver "),Ue=a(" Door Opening? "),Ge=a(" Sensor Normal? "),je=t("th",{scope:"col",class:"sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pr-4 pl-3 backdrop-blur backdrop-filter sm:pr-6 lg:pr-8"},[t("span",{class:"sr-only"},"Edit")],-1),De={class:"bg-white"},Fe={class:"flex flex-col"},He=["onClick"],Ae=t("br",null,null,-1),qe=t("br",null,null,-1),Me={class:"flex flex-col space-y-1"},ze=t("br",null,null,-1),Re={class:"grid grid-cols-[100px_minmax(100px,_1fr)_100px] gap-1"},Je={class:"font-semibold"},Qe={class:"text-blue-600 text-sm pl-1"},We={class:"pl-1"},Xe={class:"col-span-3 inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border min-w-full space-x-2"},Ye=t("span",null," Total ",-1),Ze={class:"text-blue-600 text-sm"},et={href:"#",class:"text-indigo-600 hover:text-indigo-900"},tt=a("Edit"),nt={class:"sr-only"},ot={key:0},st=t("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),lt=[st],ft={__name:"Index",props:{categories:Object,categoryGroups:Object,vends:Object,vendChannelErrors:Object},setup(d){const k=d,s=y({code:"",serialNum:"",customer_code:"",customer_name:"",categories:[],categoryGroups:[],tempHigherThan:"",vend_channel_error_id:"",sortKey:"",sortBy:!0,numberPerPage:""}),w=y([]),S=y([]),T=y([]),$=y([]);M(()=>{w.value=[{id:"",desc:"All"},{id:"errors_only",desc:"Errors Only"},...k.vendChannelErrors.data],S.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],s.value.vend_channel_error_id=w.value[0],s.value.numberPerPage=S.value[0],T.value=k.categories.data.map(u=>({id:u.id,name:u.name})),$.value=k.categoryGroups.data.map(u=>({id:u.id,name:u.name}))});function K(u){return u.vendChannels.filter(function(o){return o.capacity>0&&o.code<1e3}).reduce(function(o,h){return o+h.qty},0)}function E(u){return u.vendChannels.filter(function(o){return o.capacity>0&&o.code<1e3}).reduce(function(o,h){return o+h.capacity},0)}function I(){P.Inertia.get("/vends",{...s.value,vend_channel_error_id:s.value.vend_channel_error_id.id,categories:s.value.categories.map(u=>u.id),categoryGroups:s.value.categoryGroups.map(u=>u.id),numberPerPage:s.value.numberPerPage.id},{preserveState:!0,replace:!0})}function j(u){P.Inertia.get("/vends/"+u+"/temp")}function D(){P.Inertia.get("/vends")}function b(u){s.value.sortKey=u,s.value.sortBy=!this.filters.sortBy,I()}return(u,o)=>(g(),_(v,null,[n(N(z),{title:"Vending Machines"}),n(F,null,{header:l(()=>[J]),default:l(()=>{var h,O;return[t("div",Q,[t("div",W,[t("div",X,[n(x,{placeholderStr:"Code",modelValue:s.value.code,"onUpdate:modelValue":o[0]||(o[0]=e=>s.value.code=e)},{default:l(()=>[Y]),_:1},8,["modelValue"]),n(x,{placeholderStr:"Serial Num",modelValue:s.value.serialNum,"onUpdate:modelValue":o[1]||(o[1]=e=>s.value.serialNum=e)},{default:l(()=>[Z]),_:1},8,["modelValue"]),n(x,{placeholderStr:"Number",modelValue:s.value.tempHigherThan,"onUpdate:modelValue":o[2]||(o[2]=e=>s.value.tempHigherThan=e)},{default:l(()=>[ee]),_:1},8,["modelValue"]),t("div",null,[te,n(C,{modelValue:s.value.vend_channel_error_id,"onUpdate:modelValue":o[3]||(o[3]=e=>s.value.vend_channel_error_id=e),options:w.value,trackBy:"id",valueProp:"id",label:"desc",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),n(x,{placeholderStr:"Cust ID",modelValue:s.value.customer_code,"onUpdate:modelValue":o[4]||(o[4]=e=>s.value.customer_code=e)},{default:l(()=>[ne]),_:1},8,["modelValue"]),n(x,{placeholderStr:"Cust ID Name",modelValue:s.value.customer_name,"onUpdate:modelValue":o[5]||(o[5]=e=>s.value.customer_name=e)},{default:l(()=>[oe]),_:1},8,["modelValue"]),t("div",null,[se,n(C,{modelValue:s.value.categories,"onUpdate:modelValue":o[6]||(o[6]=e=>s.value.categories=e),options:T.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[le,n(C,{modelValue:s.value.categoryGroups,"onUpdate:modelValue":o[7]||(o[7]=e=>s.value.categoryGroups=e),options:$.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),t("div",ae,[t("div",re,[t("div",de,[n(U,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[8]||(o[8]=e=>I())},{default:l(()=>[n(N(H),{class:"h-4 w-4","aria-hidden":"true"}),ie]),_:1}),n(U,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[9]||(o[9]=e=>D())},{default:l(()=>[n(N(A),{class:"h-4 w-4","aria-hidden":"true"}),ue]),_:1})])]),t("div",ce,[t("p",me,[ge,t("span",_e,r((h=d.vends.meta.from)!=null?h:0),1),pe,t("span",fe,r((O=d.vends.meta.to)!=null?O:0),1),he,t("span",xe,r(d.vends.meta.total),1),ye]),n(C,{modelValue:s.value.numberPerPage,"onUpdate:modelValue":o[10]||(o[10]=e=>s.value.numberPerPage=e),options:S.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:I},null,8,["modelValue","options"])])])]),t("div",ve,[t("div",be,[t("div",Ve,[t("table",Ce,[t("thead",Be,[t("tr",Le,[n(p,null,{default:l(()=>[ke]),_:1}),n(V,{modelName:"code",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:o[11]||(o[11]=e=>b("code"))},{default:l(()=>[we]),_:1},8,["sortKey","sortBy"]),n(V,{modelName:"temp",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:o[12]||(o[12]=e=>b("temp"))},{default:l(()=>[Se]),_:1},8,["sortKey","sortBy"]),n(p,null,{default:l(()=>[Ie]),_:1}),n(p,null,{default:l(()=>[Ne]),_:1}),n(p,null,{default:l(()=>[Pe]),_:1}),n(p,null,{default:l(()=>[Te]),_:1}),n(V,{modelName:"temp_updated_at",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:o[13]||(o[13]=e=>b("temp_updated_at"))},{default:l(()=>[$e]),_:1},8,["sortKey","sortBy"]),n(V,{modelName:"coin_amount",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:o[14]||(o[14]=e=>b("coin_amount"))},{default:l(()=>[Ke]),_:1},8,["sortKey","sortBy"]),n(p,null,{default:l(()=>[Ee]),_:1}),n(p,null,{default:l(()=>[Oe]),_:1}),n(p,null,{default:l(()=>[Ue]),_:1}),n(p,null,{default:l(()=>[Ge]),_:1}),je])]),t("tbody",De,[(g(!0),_(v,null,B(d.vends.data,(e,c)=>(g(),_("tr",{key:e.id,class:"divide-x divide-gray-200"},[n(m,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(d.vends.meta.from+c),1)]),_:2},1032,["currentIndex","totalLength"]),n(m,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(e.code),1)]),_:2},1032,["currentIndex","totalLength"]),n(m,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",Fe,[t("button",{type:"button",class:L(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.temp>-15?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600"]]),onClick:i=>j(e.id)},r(e.is_temp_error?"Error":e.temp),11,He)])]),_:2},1032,["currentIndex","totalLength"]),n(m,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-left"},{default:l(()=>[a(r(e.latestVendBinding&&e.latestVendBinding.customer?e.latestVendBinding.customer.code:null)+" ",1),Ae,a(" "+r(e.latestVendBinding&&e.latestVendBinding.customer?e.latestVendBinding.customer.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),n(m,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-left"},{default:l(()=>[a(r(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.category?e.latestVendBinding.customer.category.name:null)+" ",1),qe,a(" "+r(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.category&&e.latestVendBinding.customer.category.category_group?e.latestVendBinding.customer.category.category_group.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),n(m,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[(g(!0),_(v,null,B(e.vendChannels.map(function(i){return i}).filter(function(i){var f;return(f=i.vendChannelErrorLogs.length)!=null?f:i.vendChannelErrorLogs}),i=>(g(),_("span",Me,[(g(!0),_(v,null,B(i.vendChannelErrorLogs.filter(function(f){return!f.is_error_cleared}),f=>(g(),_("span",{class:L(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border",[f.is_error_cleared?"bg-green-100 text-green-800":"bg-red-100 text-red-800"]])},[a(" #"+r(i.code)+", "+r(f.vendChannelError.desc)+" ",1),ze,a(" "+r(f.created_at),1)],2))),256))]))),256))]),_:2},1032,["currentIndex","totalLength"]),n(m,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",Re,[(g(!0),_(v,null,B(e.vendChannels.map(function(i){return i}).filter(function(i){return i.capacity>0&&i.code<1e3}),i=>(g(),_("div",{class:L(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border min-w-full",[i.capacity>0?"bg-gray-50 text-gray-900":"bg-red-100 text-red-800"]])},[t("div",Je," #"+r(i.code)+", ",1),t("div",Qe,r(i.capacity-i.qty)+", ",1),t("div",We,r(i.qty)+"/"+r(i.capacity),1)],2))),256)),t("div",Xe,[Ye,t("span",Ze,r(E(e)-K(e))+", ",1),t("span",null,r(K(e))+"/"+r(E(e)),1)])])]),_:2},1032,["currentIndex","totalLength"]),n(m,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(e.temp_updated_at),1)]),_:2},1032,["currentIndex","totalLength"]),n(m,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-right"},{default:l(()=>[a(r(e.coin_amount),1)]),_:2},1032,["currentIndex","totalLength"]),n(m,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(e.serial_num),1)]),_:2},1032,["currentIndex","totalLength"]),n(m,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(e.firmware_ver),1)]),_:2},1032,["currentIndex","totalLength"]),n(m,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(e.is_door_open),1)]),_:2},1032,["currentIndex","totalLength"]),n(m,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(e.is_sensor_normal),1)]),_:2},1032,["currentIndex","totalLength"]),t("td",{class:L([c!==d.vends.length-1?"border-b border-gray-200":"","relative whitespace-nowrap py-4 pr-4 pl-3 text-right text-sm font-medium sm:pr-6 lg:pr-8"])},[t("a",et,[tt,t("span",nt,", "+r(e.name),1)])],2)]))),128)),d.vends.data.length?G("",!0):(g(),_("tr",ot,lt))])]),d.vends.data.length?(g(),R(q,{key:0,links:d.vends.links,meta:d.vends.meta},null,8,["links","meta"])):G("",!0)])])])])]}),_:1})],64))}};export{ft as default};
